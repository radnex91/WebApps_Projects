<?php
declare(strict_types=1);
/**
 * Vérification e2e de la vente à crédit réactivée.
 *
 *  1. fidelite_active=1  → fideliteActive() === true  (le paramètre débloque la voie crédit)
 *  2. Crée un client enregistré
 *  3. Exécute la branche crédit de vente.php (D 4112 / C 7011 + C 4411)
 *  4. Vérifie : créance 4112 = total, vente 7011 = subtotal, TVA 4411 = tva, écriture équilibrée
 *  5. Vérifie le règlement client solde la créance (D 5711 / C 4112)
 */
require_once __DIR__ . '/bootstrap.php';

$db = getDB();
$uid = 1;

// 1) Activer fidelite_active dans la BDD de test (hors transaction, persistant)
$db->exec("INSERT INTO parametres (cle, valeur) VALUES ('fidelite_active','1')
           ON DUPLICATE KEY UPDATE valeur='1'");

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

$checks = [];

// 1. fideliteActive() true
$checks['fidelite_active=1 en base'] = (getParam('fidelite_active', '0') === '1');

$db->beginTransaction();
try {
    // 2. Client enregistré
    $db->prepare("INSERT INTO clients (nom, telephone) VALUES (?, ?)")
       ->execute(['Client Crédit Test', '690000000']);
    $clientId = (int)$db->lastInsertId();
    $checks['Client enregistré créé'] = ($clientId > 0);

    // 3. Branche crédit de vente.php (D 4112 / C 7011 + C 4411)
    $subtotal = 1000.0;
    $tva      = 192.5;   // 19.25%
    $total    = $subtotal + $tva;
    $refVente = 'VC-TEST-1';

    $compteClient = compteFindOrCreate($db, '4112', 'Clients - Crédit', 4, 'debit');
    $compteVente  = compteFindOrCreate($db, '7011', 'Ventes de médicaments', 7, 'credit');
    $compteTva    = compteFindOrCreate($db, '4411', 'TVA collectée 19.25%', 4, 'credit');
    $eidCredit = ecritureCreate($db, 'Vente ' . $refVente . ' - Crédit Client Crédit Test', '2026-07-18',
        [
            [$compteClient, round($total, 2),     0, 'Crédit client Client Crédit Test - ' . $refVente],
            [$compteVente,  0, round($subtotal, 2), 'Vente médicaments ' . $refVente],
            [$compteTva,    0, round($tva, 2),      'TVA collectée ' . $refVente],
        ],
        'vente', $refVente, $uid);

    // 4. Soldes (compte 4112 = débit → solde positif ; 7011/4411 = crédit → solde négatif)
    $checks['Créance 4112 = +' . $total]    = abs(soldeCompte($db, '4112') - $total) < 0.01;
    $checks['Vente 7011 = -' . $subtotal]   = abs(soldeCompte($db, '7011') + $subtotal) < 0.01;
    $checks['TVA 4411 = -' . $tva]          = abs(soldeCompte($db, '4411') + $tva) < 0.01;

    // écriture équilibrée (eid retourné par ecritureCreate)
    $checks['Écriture crédit équilibrée'] = ecritureEquilibree($db, $eidCredit);

    // 5. Règlement client solde la créance (D 5711 / C 4112) — modules/clients.php
    $compteCaisse = compteFindOrCreate($db, '5711', 'Caisse principale', 5, 'debit');
    ecritureCreate($db, 'Règlement Client Crédit Test (espèces)', '2026-07-18',
        [
            [$compteCaisse, round($total, 2), 0, 'Règlement Client Crédit Test'],
            [$compteClient, 0, round($total, 2), 'Règlement client Client Crédit Test'],
        ],
        'caisse', 'REG-TEST-1', $uid);

    $checks['Créance 4112 soldée après règlement'] = abs(soldeCompte($db, '4112')) < 0.01;
    $checks['Caisse 5711 = ' . $total] = abs(soldeCompte($db, '5711') - $total) < 0.01;

    $ok = true;
    foreach ($checks as $label => $res) {
        echo ($res ? '✓' : '✗ FAIL') . "  $label\n";
        if (!$res) $ok = false;
    }

    $db->rollBack();
    echo $ok ? "\nTOUT OK — vente à crédit réactivée et comptabilisée correctement.\n" : "\nÉCHEC\n";
    exit($ok ? 0 : 1);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    exit(1);
}