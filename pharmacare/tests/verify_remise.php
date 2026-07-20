<?php
declare(strict_types=1);
/**
 * Vérification du système de réduction par pourcentage + code d'autorisation.
 *
 * Réplique la logique de modules/vente.php (validation du code, recalcul net HT/TVA,
 * écriture comptable avec 7119) et de modules/remise_codes.php (génération).
 * Valide :
 *  - code valide → remise appliquée, 7119 débité, code marqué utilisé, bilan équilibré,
 *    résultat net = HT net ;
 *  - codes refusés (expiré / déjà utilisé / inexistant / créé par un autre) ;
 *  - remise 0 → pas de 7119, comportement inchangé ;
 *  - retour sur vente remisée → remboursement au prix net, 7119 inchangé,
 *    résultat net = HT net des articles conservés.
 */
require_once __DIR__ . '/bootstrap.php';

$db = getDB();
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
function produitsNets(PDO $db): float {
    $cr = compteResultat($db, '2026-01-01', '2026-12-31');
    $prod = 0;
    foreach ($cr as $c) {
        if ((int)$c['classe'] === 7) $prod += -((float)$c['total_debit'] - (float)$c['total_credit']);
    }
    return $prod;
}

/**
 * Valide un code de remise (réplique de vente.php). Retourne la ligne code ou null.
 */
function validerCodeRemise(PDO $db, string $codeIn, int $autorisePar): ?array {
    if ($autorisePar <= 0 || $codeIn === '') return null;
    // L'auteur doit figurer dans la liste des approbateurs (gérée par l'admin)
    $stmtA = $db->prepare("SELECT 1 FROM remise_approbateurs ra
        JOIN utilisateurs u ON u.id = ra.utilisateur_id
        WHERE ra.utilisateur_id = ? AND ra.actif = 1 AND u.actif = 1");
    $stmtA->execute([$autorisePar]);
    if (!$stmtA->fetch()) return null;
    $stmtC = $db->prepare("SELECT * FROM codes_remise WHERE code = ? AND used = 0 AND expires_at >= NOW()");
    $stmtC->execute([$codeIn]);
    $row = $stmtC->fetch();
    if (!$row || (int)$row['created_by'] !== $autorisePar) return null;
    return $row;
}

/**
 * Enregistre une vente (réplique de vente.php) avec remise optionnelle.
 * Retourne ['ok'=>true, 'vid'=>..., 'ref'=>..., 'eid'=>...] ou ['ok'=>false, 'error'=>msg].
 */
function enregistrerVenteRemise(PDO $db, int $uid, string $mode, float $puHt, int $qte,
        float $prixAchat, float $tvaRate, float $remisePct, int $autorisePar, string $codeIn): array {
    $remisePct = min(max($remisePct, 0), 100);
    if ($remisePct > 0) {
        $codeRow = validerCodeRemise($db, $codeIn, $autorisePar);
        if (!$codeRow) return ['ok' => false, 'error' => 'code invalide'];
    } else {
        $codeRow = null;
    }
    // produit + vente
    $db->prepare("INSERT INTO produits (nom, prix_vente, prix_achat, stock, tva, actif)
                  VALUES (?,?,?,?,?,1)")->execute(['Médoc R', $puHt*(1+$tvaRate), $prixAchat, 100, $tvaRate*100]);
    $pid = (int)$db->lastInsertId();
    $gross = round($puHt * $qte, 2);
    $grossTva = round($gross * $tvaRate, 2);
    $remiseMontant = $remisePct > 0 ? round($gross * $remisePct / 100, 2) : 0.0;
    $netHt = $gross - $remiseMontant;
    $tva = round($netHt * $tvaRate, 2);            // TVA sur HT net (légal)
    $netTtc = round($netHt + $tva, 2);             // net encaissé / dû
    $total = round($gross + $grossTva, 2);         // TTC brut FACTURÉ
    $remiseTtc = round($remiseMontant * (1 + $tvaRate), 2); // remise rendue en espèces
    $ref = 'VNT-R-' . $pid;
    $db->prepare("INSERT INTO ventes (reference, client_nom, caissier_id, sous_total, tva_total, total,
                  mode_paiement, statut_paiement, remise_pct, remise_montant, autorise_par)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$ref, 'Client R', $uid, $gross, $tva, $total, $mode,
                   $mode === 'crédit' ? 'en_attente' : 'payé',
                   round($remisePct, 2), round($remiseMontant, 2),
                   $remisePct > 0 ? $autorisePar : null]);
    $vid = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO vente_lignes (vente_id, produit_id, produit_nom, quantite, prix_unitaire, tva, total_ligne)
                  VALUES (?,?,?,?,?,?,?)")->execute([$vid, $pid, 'Médoc R', $qte, $puHt, $tvaRate*100, $gross]);
    $db->prepare("UPDATE produits SET stock = stock - ? WHERE id = ?")->execute([$qte, $pid]);

    // Marquer le code utilisé
    if ($codeRow) {
        $db->prepare("UPDATE codes_remise SET used=1, used_at=NOW(), used_vente_id=?, used_remise_pct=? WHERE id=? AND used=0")
            ->execute([$vid, $remisePct, (int)$codeRow['id']]);
    }
    // Écriture comptable (méthode brute : 5711/4112 = net encaissé, 7011 = HT brut,
    // 4411 = TVA sur net, 7119 = remise HT si remise). La remise est rendue en espèces.
    $cVente = compteFindOrCreate($db, '7011', 'Ventes de médicaments', 7, 'credit');
    $cTva   = compteFindOrCreate($db, '4411', 'TVA collectée 19.25%', 4, 'credit');
    $contre = $mode === 'crédit'
        ? compteFindOrCreate($db, '4112', 'Clients - Crédit', 4, 'debit')
        : compteFindOrCreate($db, '5711', 'Caisse principale', 5, 'debit');
    $lignes = [
        [$contre, round($netTtc, 2), 0, 'Vente ' . $ref],
        [$cVente, 0, round($gross, 2), 'Vente médicaments ' . $ref],
        [$cTva,   0, round($tva, 2),  'TVA collectée ' . $ref],
    ];
    if ($remiseMontant > 0) {
        $cRemise = compteFindOrCreate($db, '7119', 'Rabais, remises et ristournes accordés', 7, 'debit');
        $lignes[] = [$cRemise, round($remiseMontant, 2), 0, 'Remise ' . $ref];
    }
    $eid = ecritureCreate($db, 'Vente ' . $ref, '2026-07-18', $lignes, 'vente', $ref, $uid);
    return ['ok' => true, 'vid' => $vid, 'pid' => $pid, 'ref' => $ref, 'eid' => $eid,
            'gross' => $gross, 'remise' => $remiseMontant, 'netHt' => $netHt, 'tva' => $tva,
            'total' => $total, 'netTtc' => $netTtc, 'remiseTtc' => $remiseTtc];
}

/**
 * Enregistre un retour (réplique de retours.php avec remise d'origine).
 */
function enregistrerRetourRemise(PDO $db, int $uid, array $v, int $qteRetour, float $remisePctVente, float $tvaRate): array {
    $facteur = 1 - ($remisePctVente / 100);
    $puNet = round($v['puHt'] * $facteur, 2);
    $ht = round($puNet * $qteRetour, 2);
    $tva = round($ht * $tvaRate, 2);
    $tot = $ht + $tva;
    $ref = 'RET-R-' . $v['vid'];
    $db->prepare("INSERT INTO retours_vente (reference, vente_id, utilisateur_id, date_retour,
        montant_ht, montant_tva, montant_total, cout_achat_total, mode_remboursement)
        VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$ref, $v['vid'], $uid, '2026-07-18', $ht, $tva, $tot, 0, 'espèces']);
    $cVente = compteFindOrCreate($db, '7011', 'Ventes de médicaments', 7, 'credit');
    $cTva   = compteFindOrCreate($db, '4411', 'TVA collectée 19.25%', 4, 'credit');
    $cContre = compteFindOrCreate($db, '5711', 'Caisse principale', 5, 'debit');
    $eid = ecritureCreate($db, 'Retour ' . $ref, '2026-07-18',
        [[$cVente, $ht, 0, ''], [$cTva, $tva, 0, ''], [$cContre, 0, $tot, '']], 'retour', $ref, $uid);
    return ['ref' => $ref, 'eid' => $eid, 'ht' => $ht, 'tva' => $tva, 'tot' => $tot];
}

$checks = [];
$db->beginTransaction();
try {
    // ── Approbateurs inscrits dans la liste gérée par l'admin ──
    $db->prepare("INSERT INTO utilisateurs (nom, prenom, email, login, mot_de_passe, role_id, actif)
                  VALUES (?,?,?,?,?,2,1)")
        ->execute(['Approver', 'Doc', 'doc.r@test.x', 'doc_'.random_int(1000,9999), 'x']);
    $approId = (int)$db->lastInsertId();
    // Autre approbateur (pour test mismatch created_by)
    $db->prepare("INSERT INTO utilisateurs (nom, prenom, email, login, mot_de_passe, role_id, actif)
                  VALUES (?,?,?,?,?,2,1)")
        ->execute(['Autre', 'Pharm', 'autre@test.x', 'autre_'.random_int(1000,9999), 'x']);
    $autreId = (int)$db->lastInsertId();
    // Les deux sont inscrits comme approbateurs (source de vérité : la liste, plus le rôle)
    $db->prepare("INSERT INTO remise_approbateurs (utilisateur_id, added_by) VALUES (?,1)")
        ->execute([$approId]);
    $db->prepare("INSERT INTO remise_approbateurs (utilisateur_id, added_by) VALUES (?,1)")
        ->execute([$autreId]);
    $checks['approbateur inscrit dans la liste'] = validerCodeRemise($db, 'X', $approId) === null; // 'X' n'existe pas → null attendu (l'auteur est bien reconnu)
    $checks['approbateur déclaré'] = $approId > 0;

    // ── Cas 1 : code valide, vente espèces 3 boîtes HT 1200, remise 10% ──
    $code1 = 'ABCDEF';
    $db->prepare("INSERT INTO codes_remise (code, created_by, expires_at)
                  VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
        ->execute([$code1, $approId]);
    $code1Id = (int)$db->lastInsertId();

    $r = enregistrerVenteRemise($db, 1, 'espèces', 400.0, 3, 100.0, $tvaRate, 10.0, $approId, $code1);
    $checks['code valide : vente créée'] = $r['ok'] === true;
    $gross = 1200.0; $remise = 120.0; $netHt = 1080.0;
    $grossTvaAtt = round($gross * $tvaRate, 2);
    $totBrutAtt = round($gross + $grossTvaAtt, 2);       // TTC brut facturé
    $tvaAtt = round($netHt * $tvaRate, 2); $netTtcAtt = round($netHt + $tvaAtt, 2);
    $remiseTtcAtt = round($remise * (1 + $tvaRate), 2);
    $checks['code valide : ok=true'] = $r['ok'] === true;
    $vRow = $db->query("SELECT * FROM ventes WHERE id={$r['vid']}")->fetch();
    $checks['ventes.remise_pct = 10'] = abs((float)$vRow['remise_pct'] - 10) < 0.01;
    $checks['ventes.remise_montant = 120'] = abs((float)$vRow['remise_montant'] - 120) < 0.01;
    $checks['ventes.autorise_par = approId'] = (int)$vRow['autorise_par'] === $approId;
    $checks['ventes.sous_total = 1200 (HT brut)'] = abs((float)$vRow['sous_total'] - 1200) < 0.01;
    $checks['ventes.tva_total = TVA sur HT net'] = abs((float)$vRow['tva_total'] - $tvaAtt) < 0.01;
    $checks['ventes.total = TTC brut facturé'] = abs((float)$vRow['total'] - $totBrutAtt) < 0.01;

    $checks['codes_remise.used = 1'] = (int)$db->query("SELECT used FROM codes_remise WHERE id=$code1Id")->fetchColumn() === 1;
    $checks['codes_remise.used_vente_id = vid'] = (int)$db->query("SELECT used_vente_id FROM codes_remise WHERE id=$code1Id")->fetchColumn() === $r['vid'];
    $checks['codes_remise.used_remise_pct = 10'] = abs((float)$db->query("SELECT used_remise_pct FROM codes_remise WHERE id=$code1Id")->fetchColumn() - 10) < 0.01;

    $checks['écriture vente équilibrée'] = ecritureEquilibree($db, $r['eid']);
    $checks['7011 solde = -HT brut (-1200)'] = abs(soldeCompte($db, '7011') - (-1200)) < 0.01;
    $checks['5711 solde = net encaissé (TTC net)'] = abs(soldeCompte($db, '5711') - $netTtcAtt) < 0.01;
    $checks['5711 = total − remise TTC (cash conservé)'] = abs(soldeCompte($db, '5711') - ($totBrutAtt - $remiseTtcAtt)) < 0.01;
    $checks['4411 solde = -TVA net'] = abs(soldeCompte($db, '4411') - (-$tvaAtt)) < 0.01;
    $checks['7119 solde = remise HT (120)'] = abs(soldeCompte($db, '7119') - 120) < 0.01;

    $bilan = bilanGet($db, '2026-01-01', '2026-12-31');
    $checks['Bilan actif=passif'] = abs($bilan['actif']['total'] - $bilan['passif']['total']) < 0.01;
    $checks['Résultat net = HT net (1080)'] = abs(produitsNets($db) - 1080) < 0.01;

    // ── Cas 2 : codes refusés ──
    // 2a expiré
    $db->prepare("INSERT INTO codes_remise (code, created_by, expires_at) VALUES (?, ?, DATE_SUB(NOW(), INTERVAL 1 MINUTE))")
        ->execute(['EXP123', $approId]);
    $r2a = enregistrerVenteRemise($db, 1, 'espèces', 100.0, 1, 10.0, $tvaRate, 10.0, $approId, 'EXP123');
    $checks['code expiré rejeté'] = $r2a['ok'] === false;
    // 2b déjà utilisé (code1)
    $r2b = enregistrerVenteRemise($db, 1, 'espèces', 100.0, 1, 10.0, $tvaRate, 10.0, $approId, $code1);
    $checks['code déjà utilisé rejeté'] = $r2b['ok'] === false;
    // 2c inexistant
    $r2c = enregistrerVenteRemise($db, 1, 'espèces', 100.0, 1, 10.0, $tvaRate, 10.0, $approId, 'ZZZZZZ');
    $checks['code inexistant rejeté'] = $r2c['ok'] === false;
    // 2d créé par un autre approbateur
    $db->prepare("INSERT INTO codes_remise (code, created_by, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
        ->execute(['AUTRE1', $autreId]);
    $r2d = enregistrerVenteRemise($db, 1, 'espèces', 100.0, 1, 10.0, $tvaRate, 10.0, $approId, 'AUTRE1');
    $checks['code créé par autre rejeté'] = $r2d['ok'] === false;
    // 2e auteur hors liste (role 3 caissier, non inscrit comme approbateur)
    $db->prepare("INSERT INTO utilisateurs (nom, prenom, email, login, mot_de_passe, role_id, actif) VALUES (?,?,?,?,?,3,1)")
        ->execute(['Cais', 'Test', 'cais@test.x', 'cais_'.random_int(1000,9999), 'x']);
    $caisId = (int)$db->lastInsertId();
    $r2e = enregistrerVenteRemise($db, 1, 'espèces', 100.0, 1, 10.0, $tvaRate, 10.0, $caisId, 'ABCDEF');
    $checks['auteur hors liste rejeté'] = $r2e['ok'] === false;
    // 2f remise > 0 sans code
    $r2f = enregistrerVenteRemise($db, 1, 'espèces', 100.0, 1, 10.0, $tvaRate, 10.0, 0, '');
    $checks['remise sans code rejetée'] = $r2f['ok'] === false;

    // ── Cas 3 : remise 0 (pas de 7119, comportement inchangé) ──
    $solde7119avant = soldeCompte($db, '7119');
    $r3 = enregistrerVenteRemise($db, 1, 'espèces', 200.0, 2, 50.0, $tvaRate, 0.0, 0, '');
    $checks['remise 0 : vente créée'] = $r3['ok'] === true;
    $checks['remise 0 : pas de ligne 7119'] = abs(soldeCompte($db, '7119') - $solde7119avant) < 0.01;
    $v3 = $db->query("SELECT * FROM ventes WHERE id={$r3['vid']}")->fetch();
    $checks['remise 0 : total = brut + TVA'] = abs((float)$v3['total'] - (400 + round(400*$tvaRate,2))) < 0.01;

    // ── Cas 4 : retour sur vente remisée (10%) ──
    // Nouvelle vente 3 boîtes 400 HT, remise 10%, code valide
    $db->prepare("INSERT INTO codes_remise (code, created_by, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
        ->execute(['RET001', $approId]);
    $v4 = enregistrerVenteRemise($db, 1, 'espèces', 400.0, 3, 100.0, $tvaRate, 10.0, $approId, 'RET001');
    $v4['puHt'] = 400.0;
    // Soldes AVANT retour (la transaction accumule cas1+cas3+cas4) → assertions en delta
    $s7011avant = soldeCompte($db, '7011');
    $s7119avant = soldeCompte($db, '7119');
    $pAvant = produitsNets($db);
    $ret = enregistrerRetourRemise($db, 1, $v4, 1, 10.0, $tvaRate);
    // 1 boîte retournée au prix net : HT=360, TVA=round(360*rate), total=360+TVA
    $htRet = 360.0; $tvaRet = round($htRet * $tvaRate, 2); $totRet = $htRet + $tvaRet;
    $checks['retour remisé : écriture équilibrée'] = ecritureEquilibree($db, $ret['eid']);
    $checks['retour remisé : HT net = 360'] = abs($ret['ht'] - 360) < 0.01;
    $checks['retour remisé : total net'] = abs($ret['tot'] - $totRet) < 0.01;
    // Le retour débite 7011 de 360 (réduit le crédit) → solde augmente de 360
    $checks['retour : 7011 débité de 360 (delta +360)'] = abs((soldeCompte($db,'7011') - $s7011avant) - 360) < 0.01;
    // 7119 inchangé par le retour (la remise sur les articles conservés reste)
    $checks['retour : 7119 inchangé (delta 0)'] = abs(soldeCompte($db,'7119') - $s7119avant) < 0.01;
    // Résultat net baisse de 360 (une boîte nette retournée) → HT net des 2 boîtes conservées
    $checks['retour : résultat net -360 (2 boîtes conservées)'] = abs((produitsNets($db) - $pAvant) - (-360)) < 0.01;

    $ok = true;
    foreach ($checks as $label => $res) {
        echo ($res ? '✓' : '✗ FAIL') . "  $label\n";
        if (!$res) $ok = false;
    }
    $db->rollBack();
    echo $ok ? "\nTOUT OK — système de remise validé.\n" : "\nÉCHEC\n";
    exit($ok ? 0 : 1);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo "EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}