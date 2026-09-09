<?php
declare(strict_types=1);
/**
 * Vérification de la concurrence stock (bloquant #2 — lost update).
 *
 * Reproduit le scénario réel « une vente encaissée pendant qu'un utilisateur
 * ajuste le stock » et vérifie que :
 *   1. L'ajustement lit un stock FRAÎCHI sous verrou (pas le snapshot du formulaire) :
 *      le décrément de la vente n'est jamais écrasé (lost update corrigé).
 *   2. Le miroir produit_pharmacie (pharmacie 1) est synchronisé : la prochaine
 *      vente ne réécrase pas l'ajustement.
 *   3. SELECT ... FOR UPDATE sérialise réellement : un UPDATE de style vente est
 *      bloqué tant que l'ajustement tient le verrou.
 *   4. Le décrément de vente atomique (UPDATE ... WHERE stock >= ?) reste sûr :
 *      une vente ne peut pas faire passer le stock sous zéro.
 *
 * Le scénario de l'ancien code (avant correction) donnerait 47 (snapshot 42 + 5)
 * au lieu de 45 (40 après vente, + 5) : le test échouerait à l'étape 1.
 *
 * Exécution : php _archive/tests/verify_stock_concurrence.php   (BDD pharmacare_test)
 */
require_once __DIR__ . '/bootstrap.php';

$checks = [];
function check(string $label, bool $ok): void {
    global $checks;
    $checks[] = [$label, $ok];
    echo ($ok ? '  [OK]   ' : '  [FAIL] ') . $label . "\n";
}

// ── Fixture : produit dédié, supprimé en fin de script ──────────────────────
$db = getDB();
$ref = 'TESTCONC-' . substr((string)time(), -6);
$db->prepare("INSERT INTO produits (nom, reference, stock, seuil_alerte, prix_achat, prix_vente, tva, actif)
              VALUES (?, ?, 42, 10, 100.00, 150.00, 19.25, 1)")->execute(['Produit concurrence', $ref]);
$pid = (int)$db->lastInsertId();

// Miroir pharmacie principale (comme après un réglage via pharmacies.php)
$ph1 = (int)$db->query("SELECT id FROM pharmacies WHERE id = 1")->fetchColumn();
$hasPh1 = $ph1 === 1;
if ($hasPh1) {
    $db->prepare("INSERT INTO produit_pharmacie (produit_id, pharmacie_id, stock, seuil_alerte)
                  VALUES (?, 1, 42, 10)")->execute([$pid]);
}

try {
    // ── Scénario : vente encaissée (42 → 40) PUIS ajustement « entrée +5 » ──
    // Ancien code : ajustement calculait 42 + 5 = 47 depuis le snapshot → la vente
    // disparaissait. Nouveau code : lecture sous FOR UPDATE → voit 40 → écrit 45.

    // Étape 1 — la vente est enregistrée : décrément produit_pharmacie (source)
    // puis resync produits.stock (pharmacie 1) — exactement comme vente.php.
    $cVente = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $cVente->beginTransaction();
    if ($hasPh1) {
        $st = $cVente->prepare("UPDATE produit_pharmacie SET stock = stock - ? WHERE produit_id = ? AND pharmacie_id = 1 AND stock >= ?");
        $st->execute([2, $pid, 2]);
        check('Vente simulée : décrément atomique pp accepté (stock >= ? vérifié côté SQL)', $st->rowCount() === 1);
        $cVente->prepare("UPDATE produits p JOIN produit_pharmacie pp ON pp.produit_id = p.id AND pp.pharmacie_id = 1
                          SET p.stock = pp.stock WHERE p.id = ?")->execute([$pid]);
    } else {
        $st = $cVente->prepare("UPDATE produits SET stock = stock - ? WHERE id = ? AND stock >= ?");
        $st->execute([2, $pid, 2]);
        check('Vente simulée : décrément atomique accepté (stock >= ? vérifié côté SQL)', $st->rowCount() === 1);
    }
    $cVente->commit();

    // Étape 2 — l'ajustement (NOUVEAU pattern de stock_ajust.php) : pp → produits
    $cAjust = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $cAjust->exec('SET innodb_lock_wait_timeout = 5');
    $cAjust->beginTransaction();
    // 1) Verrou produit_pharmacie (pharmacie 1) — même ordre que vente.php
    $ppStock = null;
    if ($hasPh1) {
        $stPP = $cAjust->prepare("SELECT stock FROM produit_pharmacie WHERE produit_id=? AND pharmacie_id=1 FOR UPDATE");
        $stPP->execute([$pid]);
        $ppStock = $stPP->fetchColumn();
    }
    // 2) Lecture fraîche sous verrou exclusif
    $stLock = $cAjust->prepare("SELECT stock FROM produits WHERE id=? FOR UPDATE");
    $stLock->execute([$pid]);
    $luSousVerrou = (int)$stLock->fetchColumn();

    check('Ajustement : stock lu sous FOR UPDATE = 40 (décrément de la vente visible)',
          $luSousVerrou === 40);
    check('Ajustement : snapshot pp (pharmacie 1) cohérent = 40',
          (!$hasPh1) || (int)$ppStock === 40);

    $newStock = $luSousVerrou + 5; // entrée +5 calculée depuis la valeur fraîche
    $cAjust->prepare("UPDATE produits SET stock=? WHERE id=?")->execute([$newStock, $pid]);
    if ($hasPh1) {
        $cAjust->prepare("UPDATE produit_pharmacie SET stock=? WHERE produit_id=? AND pharmacie_id=1")
               ->execute([$newStock, $pid]);
    }
    $cAjust->commit();

    // Étape 3 — état final : la vente ET l'ajustement sont tous deux appliqués
    $finalProduits = (int)$db->query("SELECT stock FROM produits WHERE id=$pid")->fetchColumn();
    $finalPP = $hasPh1 ? (int)$db->query("SELECT stock FROM produit_pharmacie WHERE produit_id=$pid AND pharmacie_id=1")->fetchColumn() : null;
    check('État final produits.stock = 45 (42 − 2 vente, + 5 ajustement) — lost update éliminé',
          $finalProduits === 45);
    check('Miroir pp (pharmacie 1) = 45 — la prochaine vente ne réécrase pas l’ajustement',
          (!$hasPh1) || $finalPP === 45);

    // Étape 4 — le verrou sérialise vraiment : un UPDATE concurrent attend
    $cVente->exec('SET innodb_lock_wait_timeout = 1');
    $cVente->beginTransaction();
    $stV = $cVente->prepare("SELECT stock FROM produits WHERE id=? FOR UPDATE");
    $stV->execute([$pid]);
    $stV->fetchColumn(); // maintient le verrou
    $bloque = false;
    try {
        $cAjust->exec('SET innodb_lock_wait_timeout = 1');
        $cAjust->beginTransaction();
        $cAjust->prepare("SELECT stock FROM produits WHERE id=? FOR UPDATE")->execute([$pid]);
        // non bloqué (ne devrait pas arriver : verrou détenu par $cVente)
        $cAjust->rollBack();
    } catch (PDOException $e) {
        $bloque = (int)$e->errorInfo[1] === 1205; // lock wait timeout
        $cAjust->rollBack();
    }
    check('SELECT ... FOR UPDATE sérialise : la 2e connexion est bloquée (timeout 1205)', $bloque);
    $cVente->rollBack();

    // Étape 5 — le garde stock >= ? empêche toute vente en stock insuffisant
    $st = $db->prepare("UPDATE produits SET stock = stock - 999 WHERE id = ? AND stock >= 999");
    $st->execute([$pid]);
    check('Décrément atomique : UPDATE refusé si stock insuffisant (rowCount = 0)', $st->rowCount() === 0);
} finally {
    // ── Nettoyage complet de la fixture ──
    try {
        $db->prepare("DELETE FROM produit_pharmacie WHERE produit_id=? AND pharmacie_id=1")->execute([$pid]);
        $db->prepare("DELETE FROM produits WHERE id=?")->execute([$pid]);
    } catch (Throwable $e) { /* nettoyage best-effort */ }
}

// ── Résultat ──
$echecs = array_filter($checks, fn($c) => !$c[1]);
$passe = count($checks) - count($echecs);
echo "\n" . $passe . "/" . count($checks) . " vérifications OK\n";
if ($echecs) {
    echo "ÉCHEC — concurrence stock non maîtrisée\n";
    exit(1);
}
echo "SUCCÈS — pas de lost update, miroir synchronisé, verrouillage effectif\n";