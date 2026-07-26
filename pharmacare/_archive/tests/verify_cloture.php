<?php
declare(strict_types=1);
/**
 * Vérification de la clôture d'exercice (détermination du résultat).
 * Sème une vente + une charge sur 2026, clôture, puis vérifie :
 *  - classes 6 et 7 soldées (net 0)
 *  - compte 12 = résultat attendu
 *  - exercice 2026 cloture=1, écritures verrouillées
 *  - exercice 2027 créé
 *  - saisie sur 2026 (clôturé) → Exception
 */
require_once __DIR__ . '/bootstrap.php';

$db = getDB();
$uid = 1;

function soldeClasse(PDO $db, int $classe, string $debut, string $fin): float {
    $s = $db->prepare("SELECT COALESCE(SUM(el.debit),0)-COALESCE(SUM(el.credit),0)
                        FROM ecriture_lignes el
                        JOIN plan_comptable pc ON el.compte_id=pc.id
                        JOIN ecritures e ON el.ecriture_id=e.id
                        WHERE pc.classe=? AND e.date_ecriture>=? AND e.date_ecriture<=?");
    $s->execute([$classe, $debut, $fin]);
    return (float)$s->fetchColumn();
}
function soldeCompte(PDO $db, string $code): float {
    $s = $db->prepare("SELECT COALESCE(SUM(el.debit),0)-COALESCE(SUM(el.credit),0)
                        FROM ecriture_lignes el JOIN plan_comptable pc ON el.compte_id=pc.id
                        WHERE pc.compte=?");
    $s->execute([$code]);
    return (float)$s->fetchColumn();
}

$db->beginTransaction();
try {
    // Vente : D 5711 1200 / C 7011 1000 + C 4411 200  → produit 1000
    $cCaisse = compteFindOrCreate($db, '5711', 'Caisse principale', 5, 'debit');
    $cVente  = compteFindOrCreate($db, '7011', 'Ventes de médicaments', 7, 'credit');
    $cTva    = compteFindOrCreate($db, '4411', 'TVA collectée 19.25%', 4, 'credit');
    ecritureCreate($db, 'Vente TEST-C1', '2026-05-10',
        [[$cCaisse, 1200, 0, ''], [$cVente, 0, 1000, ''], [$cTva, 0, 200, '']],
        'vente', 'TEST-C1', $uid);

    // Charge : D 658 300 / C 5711 300 → charge 300
    $cCharge = compteFindOrCreate($db, '658', 'Charges diverses', 6, 'debit');
    ecritureCreate($db, 'Charge TEST-C2', '2026-05-12',
        [[$cCharge, 300, 0, ''], [$cCaisse, 0, 300, '']],
        'manuel', 'TEST-C2', $uid);

    // Résultat attendu : 1000 - 300 = 700
    $attendu = 700.0;

    // Trouver l'id de l'exercice 2026
    $ex = $db->query("SELECT id FROM exercices WHERE code='2026'")->fetch();
    $exId = (int)$ex['id'];

    $r = clotureExercice($db, $exId, $uid);

    $checks = [];
    $checks['Résultat = 700']              = abs($r['resultat'] - $attendu) < 0.01;
    $checks['Classe 6 soldée (net 0)']    = abs(soldeClasse($db, 6, '2026-01-01', '2026-12-31')) < 0.01;
    $checks['Classe 7 soldée (net 0)']    = abs(soldeClasse($db, 7, '2026-01-01', '2026-12-31')) < 0.01;
    $checks['Compte 12 = 700 (crédit)']   = abs(soldeCompte($db, '12') - (-$attendu)) < 0.01;

    // exercice 2026 cloturé
    $ex26 = $db->query("SELECT cloture FROM exercices WHERE code='2026'")->fetch();
    $checks['Exercice 2026 cloture=1']    = ((int)$ex26['cloture']) === 1;

    // écritures de l'exercice verrouillées
    $nbVerrou = (int)$db->query("SELECT COUNT(*) FROM ecritures WHERE exercice_id=$exId AND verrouillee=1")->fetchColumn();
    $nbTotal  = (int)$db->query("SELECT COUNT(*) FROM ecritures WHERE exercice_id=$exId")->fetchColumn();
    $checks['Toutes écritures verrouillées'] = ($nbTotal > 0 && $nbVerrou === $nbTotal);

    // exercice 2027 créé
    $ex27 = $db->query("SELECT id FROM exercices WHERE code='2027'")->fetch();
    $checks['Exercice 2027 créé']         = ($ex27 !== false);
    $checks['next_code = 2027']           = ($r['next_code'] === '2027');

    // saisie sur 2026 clôturé → Exception
    $threw = false;
    try {
        ecritureCreate($db, 'Tentative post-clôture', '2026-07-01',
            [[$cCaisse, 10, 0, ''], [$cVente, 0, 10, '']], 'manuel', 'TEST-X', $uid);
    } catch (Exception $e) {
        $threw = true;
    }
    $checks['Saisie sur exercice clôturé bloquée'] = $threw;

    $ok = true;
    foreach ($checks as $label => $res) {
        echo ($res ? '✓' : '✗ FAIL') . "  $label\n";
        if (!$res) $ok = false;
    }

    $db->rollBack();
    echo $ok ? "\nTOUT OK — clôture d'exercice validée.\n" : "\nÉCHEC\n";
    exit($ok ? 0 : 1);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    exit(1);
}