<?php
declare(strict_types=1);
/**
 * Vérification offline-first : idempotence des ventes (client_ref).
 *
 * 1. INSERT d'une vente avec client_ref → OK
 * 2. La requête de dédoublonnage de vente.php retrouve la vente
 * 3. Un second INSERT avec le MÊME client_ref est refusé (PK UNIQUE)
 *    → le rejeu hors ligne ne peut jamais créer de doublon
 * 4. Plusieurs ventes avec client_ref NULL coexistent (ventes anciennes)
 */
require_once __DIR__ . '/bootstrap.php';

$checks = [];
function check(string $label, bool $ok): void {
    global $checks;
    $checks[] = [$label, $ok];
    echo ($ok ? '  [OK]   ' : '  [FAIL] ') . $label . "\n";
}

$db = getDB();
$ref1 = 'OFFL-' . date('His');
$ref2 = 'OFFL-' . (date('His') + 1);
$ref3 = 'OFFL-' . (date('His') + 2);
$refX = 'OFFL-' . substr((string)time(), -6);
$cref = 'pos-test-' . bin2hex(random_bytes(8));

try {
    // 1) Vente enregistrée avec sa clé d'idempotence
    $db->prepare("INSERT INTO ventes (reference, mode_paiement, total, client_ref) VALUES (?,?,?,?)")
       ->execute([$ref1, 'espèces', 1500.00, $cref]);
    check('Vente enregistrée avec client_ref (UUID terminal)', true);

    // 2) Dédoublonnage : la requête exacte de vente.php (avant rate-limit)
    $st = $db->prepare("SELECT reference FROM ventes WHERE client_ref = ? LIMIT 1");
    $st->execute([$cref]);
    $found = (string)$st->fetchColumn();
    check('Dédoublement : le rejeu retrouve la vente existante (' . $found . ')', $found === $ref1);

    // 3) Anti-doublon : même client_ref → insertion refusée par l'index UNIQUE
    $dupBlocked = false;
    try {
        $db->prepare("INSERT INTO ventes (reference, mode_paiement, total, client_ref) VALUES (?,?,?,?)")
           ->execute([$refX, 'espèces', 999.00, $cref]);
    } catch (PDOException $e) {
        $dupBlocked = ((int)($e->errorInfo[1] ?? 0) === 1062); // duplicate entry
    }
    check('Anti-doublon : second INSERT avec le même client_ref refusé (1062)', $dupBlocked);

    // 4) Ventes historiques (client_ref NULL) : coexistence autorisée
    $db->prepare("INSERT INTO ventes (reference, mode_paiement, total, client_ref) VALUES (?,?,?,NULL)")
       ->execute([$ref2, 'espèces', 100.00]);
    $db->prepare("INSERT INTO ventes (reference, mode_paiement, total, client_ref) VALUES (?,?,?,NULL)")
       ->execute([$ref3, 'espèces', 200.00]);
    $n = (int)$db->query("SELECT COUNT(*) FROM ventes WHERE client_ref IS NULL")->fetchColumn();
    check('Compatibilité : plusieurs ventes sans client_ref (NULL) coexistent', $n >= 2);
} finally {
    // Nettoyage
    foreach ([$ref1, $ref2, $ref3, $refX] as $r) {
        try { $db->prepare("DELETE FROM ventes WHERE reference = ?")->execute([$r]); } catch (Throwable $e) {}
    }
}

$echecs = array_filter($checks, fn($c) => !$c[1]);
$passe = count($checks) - count($echecs);
echo "\n" . $passe . "/" . count($checks) . " vérifications OK\n";
if ($echecs) {
    echo "ÉCHEC — idempotence offline non garantie\n";
    exit(1);
}
echo "SUCCÈS — le rejeu hors ligne ne peut pas dupliquer une vente\n";