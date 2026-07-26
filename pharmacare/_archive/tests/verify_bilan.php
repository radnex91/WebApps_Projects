<?php
declare(strict_types=1);
/**
 * Vérification du point 6 : le bilan réinjecte le résultat net au passif
 * (compte 12) et Actif = Passif à tout moment.
 */
require_once __DIR__ . '/bootstrap.php';

$db = getDB();
$uid = 1;
$today = '2026-06-15';

$db->beginTransaction();
try {
    // Vente : D 5711 (1200) / C 7011 (1000) + C 4411 (200)
    $cCaisse = compteFindOrCreate($db, '5711', 'Caisse principale', 5, 'debit');
    $cVente  = compteFindOrCreate($db, '7011', 'Ventes de médicaments', 7, 'credit');
    $cTva    = compteFindOrCreate($db, '4411', 'TVA collectée 19.25%', 4, 'credit');
    ecritureCreate($db, 'Vente TEST-B1', $today,
        [[$cCaisse, 1200, 0, 'caisse'], [$cVente, 0, 1000, 'vente'], [$cTva, 0, 200, 'tva']],
        'vente', 'TEST-B1', $uid);

    $bilan = bilanGet($db, '2026-01-01', '2026-12-31');
    $diff = $bilan['actif']['total'] - $bilan['passif']['total'];

    $checks = [];
    $checks['Actif = 1200']    = abs($bilan['actif']['total'] - 1200.0) < 0.01;
    $checks['Passif = 1200']   = abs($bilan['passif']['total'] - 1200.0) < 0.01;
    $checks['Actif = Passif']  = abs($diff) < 0.01;

    $trouve12 = false;
    foreach ($bilan['passif']['comptes'] as $p) {
        if ($p['compte'] === '12' && abs($p['montant'] - 1000.0) < 0.01) $trouve12 = true;
    }
    $checks['Ligne 12 (résultat 1000) au passif'] = $trouve12;

    $ok = true;
    foreach ($checks as $label => $res) {
        echo ($res ? '✓' : '✗ FAIL') . "  $label\n";
        if (!$res) $ok = false;
    }
    echo "\nActif=" . $bilan['actif']['total'] . " Passif=" . $bilan['passif']['total'] . "\n";

    $db->rollBack();
    echo $ok ? "\nTOUT OK — bilan équilibré avec résultat au passif.\n" : "\nÉCHEC\n";
    exit($ok ? 0 : 1);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    exit(1);
}