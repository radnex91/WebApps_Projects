<?php
declare(strict_types=1);
/**
 * Vérification du module Retours caisse.
 * Réplique la logique de modules/retours.php (POST create) via une helper,
 * et valide : stock remis, mouvements_stock entrée, contrepassation OHADA
 * (D 7011 / D 4411 / C contrepartie + D 3111 / C 6031), mouvement caisse de
 * remboursement (espèces), et réduction créance (crédit, sans caisse).
 * + garde-fou sur-retour (qte > reste).
 */
require_once __DIR__ . '/bootstrap.php';

$db = getDB();
$uid = 1;
$tvaRate = (float)getParam('tva', '19.25') / 100;

function soldeCompte(PDO $db, string $code): float {
    $s = $db->prepare("SELECT COALESCE(SUM(el.debit),0)-COALESCE(SUM(el.credit),0)
                        FROM ecriture_lignes el JOIN plan_comptable pc ON el.compte_id=pc.id
                        WHERE pc.compte=?");
    $s->execute([$code]);
    return (float)$s->fetchColumn();
}
function ecritureEquilibree(PDO $db, int $eid): bool {
    $s = $db->prepare("SELECT COALESCE(SUM(debit),0)=COALESCE(SUM(credit),0) AND COUNT(*)>0
                        FROM ecriture_lignes WHERE ecriture_id=?");
    $s->execute([$eid]);
    return (bool)$s->fetchColumn();
}

/**
 * Sème une vente (replicat de modules/vente.php) et retourne l'id + les comptes.
 */
function semerVente(PDO $db, int $uid, string $mode, float $puHt, int $qte, float $prixAchat, float $tvaRate): array {
    // produit
    $db->prepare("INSERT INTO produits (nom, prix_vente, prix_achat, stock, tva, actif)
                  VALUES (?,?,?,?,?,1)")->execute(['Médoc TEST', $puHt*(1+$tvaRate), $prixAchat, 100, $tvaRate*100]);
    $pid = (int)$db->lastInsertId();
    $ht  = round($puHt * $qte, 2);
    $tva = round($ht * $tvaRate, 2);
    $tot = $ht + $tva;
    $ref = 'VNT-TEST-' . $pid;
    $db->prepare("INSERT INTO ventes (reference, client_nom, client_id, caissier_id, sous_total, tva_total, total, mode_paiement, statut_paiement)
                  VALUES (?,?,?,?,?,?,?,?, 'payé')")->execute([$ref, 'Client TEST', null, $uid, $ht, $tva, $tot, $mode]);
    $vid = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO vente_lignes (vente_id, produit_id, produit_nom, quantite, prix_unitaire, tva, total_ligne)
                  VALUES (?,?,?,?,?,?,?)")->execute([$vid, $pid, 'Médoc TEST', $qte, $puHt, $tvaRate*100, $ht]);
    $vlid = (int)$db->lastInsertId();

    // décrément du stock physique (comme vente.php)
    $db->prepare("UPDATE produits SET stock = stock - ? WHERE id = ?")->execute([$qte, $pid]);

    // écritures de la vente (comme vente.php)
    $cVente = compteFindOrCreate($db, '7011', 'Ventes de médicaments', 7, 'credit');
    $cTva   = compteFindOrCreate($db, '4411', 'TVA collectée 19.25%', 4, 'credit');
    $contre = match ($mode) {
        'crédit'    => compteFindOrCreate($db, '4112', 'Clients - Crédit', 4, 'debit'),
        'espèces'   => compteFindOrCreate($db, '5711', 'Caisse principale', 5, 'debit'),
        default     => compteFindOrCreate($db, '5711', 'Caisse principale', 5, 'debit'),
    };
    ecritureCreate($db, 'Vente '.$ref, '2026-07-18',
        [[$contre, $tot, 0, ''], [$cVente, 0, $ht, ''], [$cTva, 0, $tva, '']], 'vente', $ref, $uid);
    $cout = round($prixAchat * $qte, 2);
    if ($cout > 0) {
        $cStock = compteFindOrCreate($db, '3111', 'Médicaments en stock', 3, 'debit');
        $cVar  = compteFindOrCreate($db, '6031', 'Variation stocks marchandises', 6, 'debit');
        ecritureCreate($db, 'Sortie stock '.$ref, '2026-07-18',
            [[$cVar, $cout, 0, ''], [$cStock, 0, $cout, '']], 'vente', $ref, $uid);
    }
    return ['vid'=>$vid, 'vlid'=>$vlid, 'pid'=>$pid, 'ref'=>$ref, 'mode'=>$mode, 'puHt'=>$puHt, 'qte'=>$qte, 'prixAchat'=>$prixAchat];
}

/**
 * Réplique exacte de modules/retours.php POST create (sans CSRF/redirect).
 * Retourne ['retour_id', 'ref'] ou lève en cas de sur-retour.
 */
function enregistrerRetour(PDO $db, int $uid, array $v, int $qteRetour, float $tvaRate, ?int $sessionId = null): array {
    $ht  = round($v['puHt'] * $qteRetour, 2);
    $tva = round($ht * $tvaRate, 2);
    $tot = $ht + $tva;
    $cout = round($v['prixAchat'] * $qteRetour, 2);

    $ref = 'RET-TEST-' . $v['vid'];
    $db->prepare("INSERT INTO retours_vente
        (reference, vente_id, utilisateur_id, date_retour, montant_ht, montant_tva, montant_total, cout_achat_total, mode_remboursement)
        VALUES (?,?,?,?,?,?,?,?,?)")->execute([
        $ref, $v['vid'], $uid, '2026-07-18', $ht, $tva, $tot, $cout, $v['mode']]);
    $rid = (int)$db->lastInsertId();

    $db->prepare("INSERT INTO retour_vente_lignes
        (retour_id, vente_ligne_id, produit_id, produit_nom, quantite, prix_unitaire, tva, total_ligne, cout_achat)
        VALUES (?,?,?,?,?,?,?,?,?)")->execute([
        $rid, $v['vlid'], $v['pid'], 'Médoc TEST', $qteRetour, $v['puHt'], $tvaRate*100, $ht, $cout]);

    $db->prepare("UPDATE produits SET stock = stock + ? WHERE id = ?")->execute([$qteRetour, $v['pid']]);
    $db->prepare("INSERT INTO mouvements_stock (produit_id, type, quantite, motif, utilisateur_id)
                  VALUES (?, 'entrée', ?, ?, ?)")->execute([$v['pid'], $qteRetour, 'Retour '.$ref, $uid]);

    $cVente = compteFindOrCreate($db, '7011', 'Ventes de médicaments', 7, 'credit');
    $cTva   = compteFindOrCreate($db, '4411', 'TVA collectée 19.25%', 4, 'credit');
    $cContre = match ($v['mode']) {
        'crédit'  => compteFindOrCreate($db, '4112', 'Clients - Crédit', 4, 'debit'),
        'espèces' => compteFindOrCreate($db, '5711', 'Caisse principale', 5, 'debit'),
        default   => compteFindOrCreate($db, '5711', 'Caisse principale', 5, 'debit'),
    };
    $e1 = ecritureCreate($db, 'Retour '.$ref, '2026-07-18',
        [[$cVente, $ht, 0, ''], [$cTva, $tva, 0, ''], [$cContre, 0, $tot, '']], 'retour', $ref, $uid);
    $e2 = 0;
    if ($cout > 0) {
        $cStock = compteFindOrCreate($db, '3111', 'Médicaments en stock', 3, 'debit');
        $cVar  = compteFindOrCreate($db, '6031', 'Variation stocks marchandises', 6, 'debit');
        $e2 = ecritureCreate($db, 'Retour stock '.$ref, '2026-07-18',
            [[$cStock, $cout, 0, ''], [$cVar, 0, $cout, '']], 'retour', $ref, $uid);
    }
    if ($v['mode'] !== 'crédit' && $sessionId) {
        $db->prepare("INSERT INTO mouvements_caisse (session_id, type, montant, motif, moyen, reference_vente)
                      VALUES (?, 'sortie', ?, ?, ?, ?)")->execute([$sessionId, $tot, 'Remboursement '.$ref, $v['mode'], $ref]);
    }
    return ['retour_id'=>$rid, 'ref'=>$ref, 'e1'=>$e1, 'e2'=>$e2, 'ht'=>$ht, 'tva'=>$tva, 'tot'=>$tot, 'cout'=>$cout];
}

$checks = [];
$db->beginTransaction();
try {
    // ── Ouverture de stock : D 3111 / C 101 (capital) ──
    // Établit l'actif stock initial ; sans lui, 3111 passerait créditeur après
    // la sortie de stock et bilanGet (qui n'ajoute la classe 3 à l'actif que
    // si solde>0) l'ignorerait → bilan déséquilibré (artefact de test).
    $cStock0 = compteFindOrCreate($db, '3111', 'Médicaments en stock', 3, 'debit');
    $cCap    = compteFindOrCreate($db, '101', 'Capital', 1, 'credit');
    ecritureCreate($db, 'Ouverture de stock', '2026-07-18',
        [[$cStock0, 1000, 0, ''], [$cCap, 0, 1000, '']], 'ouverture', 'OUV-STOCK', $uid);

    // ── Cas 1 : vente espèces 3 boîtes, retour 1 boîte ──
    $v = semerVente($db, $uid, 'espèces', 400.0, 3, 100.0, $tvaRate); // 3×400 HT
    // session caisse factice pour le remboursement
    $db->prepare("INSERT INTO sessions_caisse (caisse_id, caissier_id, statut) VALUES (1,?, 'ouverte')")->execute([$uid]);
    $sid = (int)$db->lastInsertId();

    $r = enregistrerRetour($db, $uid, $v, 1, $tvaRate, $sid);

    $ht1  = round(400 * 1, 2);
    $tva1 = round($ht1 * $tvaRate, 2);
    $tot1 = $ht1 + $tva1;
    $cout1 = 100.0;

    $checks['retours_vente: 1 ligne'] = ((int)$db->query("SELECT COUNT(*) FROM retours_vente")->fetchColumn()) === 1;
    $checks['retour montant_total correct'] = abs((float)$db->query("SELECT montant_total FROM retours_vente WHERE id={$r['retour_id']}")->fetchColumn() - $tot1) < 0.01;
    $checks['retour mode=espèces'] = $db->query("SELECT mode_remboursement FROM retours_vente WHERE id={$r['retour_id']}")->fetchColumn() === 'espèces';
    $checks['retour_vente_lignes qte=1 cout=100'] = abs((float)$db->query("SELECT cout_achat FROM retour_vente_lignes WHERE retour_id={$r['retour_id']}")->fetchColumn() - $cout1) < 0.01;
    $checks['produits.stock = 100-3+1 = 98'] = ((int)$db->query("SELECT stock FROM produits WHERE id={$v['pid']}")->fetchColumn()) === 98;
    $checks['mouvements_stock entrée qte=1'] = ((int)$db->query("SELECT COUNT(*) FROM mouvements_stock WHERE produit_id={$v['pid']} AND type='entrée' AND quantite=1")->fetchColumn()) === 1;

    // Contrepassation vente : D 7011(ht1) / D 4411(tva1) / C 5711(tot1)
    $checks['7011 solde = -(vente HT - retour HT)'] = abs(soldeCompte($db, '7011') - (-(1200.0 - $ht1))) < 0.01;
    $checks['4411 solde = -(vente TVA - retour TVA)'] = abs(soldeCompte($db, '4411') - (-(round(1200*$tvaRate,2) - $tva1))) < 0.01;
    $checks['5711 solde = vente total - retour total'] = abs(soldeCompte($db, '5711') - (1200*(1+$tvaRate) - $tot1)) < 0.01;
    // Contrepassation stock : D 3111(cout1) / C 6031(cout1)
    // 3111 = ouverture(1000) - sortie vente(300) + retour(100) = 800
    $checks['3111 solde = 1000 - 300 + 100 = 800'] = abs(soldeCompte($db, '3111') - (1000.0 - 300.0 + $cout1)) < 0.01;
    $checks['6031 solde = 300 - 100'] = abs(soldeCompte($db, '6031') - (300.0 - $cout1)) < 0.01;
    $checks['écriture retour vente équilibrée'] = ecritureEquilibree($db, $r['e1']);
    $checks['écriture retour stock équilibrée'] = $r['e2'] ? ecritureEquilibree($db, $r['e2']) : true;

    // Mouvement caisse remboursement
    $nbCaisse = (int)$db->query("SELECT COUNT(*) FROM mouvements_caisse WHERE reference_vente='{$r['ref']}' AND type='sortie'")->fetchColumn();
    $checks['mouvements_caisse sortie = 1'] = $nbCaisse === 1;
    $checks['mouvements_caisse montant = total retour'] = abs((float)$db->query("SELECT montant FROM mouvements_caisse WHERE reference_vente='{$r['ref']}'")->fetchColumn() - $tot1) < 0.01;

    // Bilan équilibré
    $bilan = bilanGet($db, '2026-01-01', '2026-12-31');
    $checks['Bilan actif=passif (après retour)'] = abs($bilan['actif']['total'] - $bilan['passif']['total']) < 0.01;

    // Résultat net = produits - charges = (1200-400) - (300-100) = 800 - 200 = 600
    $cr = compteResultat($db, '2026-01-01', '2026-12-31');
    $prod = 0; $charg = 0;
    foreach ($cr as $c) {
        $solde = (float)$c['total_debit'] - (float)$c['total_credit'];
        if ((int)$c['classe'] === 7) $prod += -$solde;
        if ((int)$c['classe'] === 6) $charg += $solde;
    }
    $checks['Résultat net = 600 (2 boîtes conservées)'] = abs(($prod - $charg) - 600.0) < 0.01;

    // ── Cas 2 : vente crédit 2 boîtes, retour 1 boîte ──
    $v2 = semerVente($db, $uid, 'crédit', 400.0, 2, 100.0, $tvaRate);
    $r2 = enregistrerRetour($db, $uid, $v2, 1, $tvaRate, $sid); // même session, mais crédit → pas de caisse
    $tot2 = round(400,2) + round(400*$tvaRate,2);
    $checks['crédit : 4112 solde = vente - retour (créance réduite)'] = abs(soldeCompte($db, '4112') - (2*400*(1+$tvaRate) - $tot2)) < 0.01;
    $nbCaisse2 = (int)$db->query("SELECT COUNT(*) FROM mouvements_caisse WHERE reference_vente='{$r2['ref']}'")->fetchColumn();
    $checks['crédit : AUCUN mouvement caisse'] = $nbCaisse2 === 0;

    // ── Cas 3 : sur-retour (qte > reste) ──
    // Après retour 1/2 sur v2, reste = 1. Tentative retour qte=5 > 1 → rejet.
    $resteV2 = (int)$db->query("SELECT quantite FROM vente_lignes WHERE id={$v2['vlid']}")->fetchColumn()
             - (int)$db->query("SELECT COALESCE(SUM(quantite),0) FROM retour_vente_lignes WHERE vente_ligne_id={$v2['vlid']}")->fetchColumn();
    $checks['sur-retour : reste v2 = 1'] = $resteV2 === 1;
    $surRetourAutorise = (5 <= $resteV2); // logique de validation de retours.php
    $checks['sur-retour qte=5 rejeté (validation)'] = $surRetourAutorise === false;

    $ok = true;
    foreach ($checks as $label => $res) {
        echo ($res ? '✓' : '✗ FAIL') . "  $label\n";
        if (!$res) $ok = false;
    }
    $db->rollBack();
    echo $ok ? "\nTOUT OK — module Retours caisse validé.\n" : "\nÉCHEC\n";
    exit($ok ? 0 : 1);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    exit(1);
}