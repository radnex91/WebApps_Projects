<?php
declare(strict_types=1);
/**
 * Vérification d'intégration des correctifs comptables (points 1 à 4).
 * Exercice : sur la BDD de test, simule une vente (sortie stock), un achat
 * (entrée stock), un règlement client et un mouvement de caisse, puis vérifie
 * que chaque écriture est équilibrée et que les comptes 3111/6031/4112/5711/471
 * sont correctement mouvementés.
 */
require_once __DIR__ . '/bootstrap.php';

$db = getDB();
$uid = 1;
$today = date('Y-m-d');

function balanced(PDO $db, int $eid): bool {
    $s = $db->prepare("SELECT SUM(debit) d, SUM(credit) c FROM ecriture_lignes WHERE ecriture_id=?");
    $s->execute([$eid]);
    $r = $s->fetch();
    return abs((float)$r['d'] - (float)$r['c']) < 0.01;
}

function solde(PDO $db, string $code): array {
    $s = $db->prepare("SELECT COALESCE(SUM(el.debit),0) d, COALESCE(SUM(el.credit),0) c
                       FROM ecriture_lignes el JOIN plan_comptable pc ON el.compte_id=pc.id
                       WHERE pc.compte=?");
    $s->execute([$code]);
    return $s->fetch();
}

$db->beginTransaction();
try {
    // --- Point 2 : sortie de stock à la vente (D 6031 / C 3111) ---
    $cout = 1000.00;
    $cStock = compteFindOrCreate($db, '3111', 'Médicaments en stock', 3, 'debit');
    $cVar   = compteFindOrCreate($db, '6031', 'Variation stocks marchandises', 6, 'debit');
    $eid1 = ecritureCreate($db, 'Sortie stock vente TEST-V1', $today,
        [[$cVar, $cout, 0, 'sortie'], [$cStock, 0, $cout, 'stock']], 'vente', 'TEST-V1', $uid);

    // --- Point 1 : entrée stock à l'achat (D 3111 / C 6031) ---
    $eid2 = ecritureCreate($db, 'Entrée stock achat TEST-C1', $today,
        [[$cStock, 2500.00, 0, 'entrée'], [$cVar, 0, 2500.00, 'variation']], 'commande', 'TEST-C1', $uid);

    // --- Point 3 : règlement client (D 5711 / C 4112) ---
    $cCaisse = compteFindOrCreate($db, '5711', 'Caisse principale', 5, 'debit');
    $cClient = compteFindOrCreate($db, '4112', 'Clients - Crédit', 4, 'debit');
    $eid3 = ecritureCreate($db, 'Règlement client TEST-R1', $today,
        [[$cCaisse, 500.00, 0, 'encaissement'], [$cClient, 0, 500.00, 'client']], 'caisse', 'TEST-R1', $uid);

    // --- Point 4 : fond de caisse + mouvement manuel (D 5711 / C 471) ---
    $cAttente = compteFindOrCreate($db, '471', 'Compte d\'attente', 4, 'credit');
    $eid4 = ecritureCreate($db, 'Fond initial TEST-F1', $today,
        [[$cCaisse, 200.00, 0, 'fond'], [$cAttente, 0, 200.00, 'attente']], 'caisse', 'TEST-F1', $uid);
    $eid5 = ecritureCreate($db, 'Retrait caisse TEST-M1', $today,
        [[$cAttente, 150.00, 0, 'à régulariser'], [$cCaisse, 0, 150.00, 'retrait']], 'caisse', 'TEST-M1', $uid);

    // --- Vérifications ---
    $checks = [
        'Vente équilibrée'        => balanced($db, (int)$eid1),
        'Achat équilibré'         => balanced($db, (int)$eid2),
        'Règlement équilibré'     => balanced($db, (int)$eid3),
        'Fond caisse équilibré'   => balanced($db, (int)$eid4),
        'Mouvement caisse équilbr'=> balanced($db, (int)$eid5),
    ];

    // Stock net : entrée 2500 - sortie 1000 = 1500 (D)
    $s3111 = solde($db, '3111');
    $stockNet = (float)$s3111['d'] - (float)$s3111['c'];
    $checks['Stock 3111 net = 1500'] = abs($stockNet - 1500.00) < 0.01;

    // Variation 6031 : D sortie 1000 - C entrée 2500 = -1500 (réduction de charge)
    $s6031 = solde($db, '6031');
    $varNet = (float)$s6031['d'] - (float)$s6031['c'];
    $checks['Variation 6031 net = -1500'] = abs($varNet - (-1500.00)) < 0.01;

    // Client 4112 : C 500 → solde créditeur -500 (créance soldée)
    $s4112 = solde($db, '4112');
    $checks['Client 4112 soldé (crédit 500)'] = abs((float)$s4112['c'] - 500.00) < 0.01;

    // Caisse 5711 : D(500+200) - C(150) = 550
    $s5711 = solde($db, '5711');
    $caisseNet = (float)$s5711['d'] - (float)$s5711['c'];
    $checks['Caisse 5711 net = 550'] = abs($caisseNet - 550.00) < 0.01;

    // 471 : C(200) - D(150) = -50 côté crédit net 50
    $s471 = solde($db, '471');
    $attenteNet = (float)$s471['c'] - (float)$s471['d'];
    $checks['Attente 471 net crédit = 50'] = abs($attenteNet - 50.00) < 0.01;

    $ok = true;
    foreach ($checks as $label => $res) {
        echo ($res ? '✓' : '✗ FAIL') . "  $label\n";
        if (!$res) $ok = false;
    }

    $db->rollBack(); // on ne persiste pas les écritures de test
    echo $ok ? "\nTOUT OK — correctifs comptables validés.\n" : "\nÉCHEC — vérifier ci-dessus.\n";
    exit($ok ? 0 : 1);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    exit(1);
}