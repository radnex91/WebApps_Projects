<?php
declare(strict_types=1);
/**
 * PharmaCare — Migration idempotente des index de performance des listes.
 *
 * Ajoute les index composites manquants qui accélèrent :
 *   - stock.php     : MAX(created_at) par produit/type sur mouvements_stock
 *   - magasin.php   : idem sur mouvements_magasin + ORDER BY nom WHERE actif=1
 *   - commandes.php : ORDER BY created_at
 *   - retours.php   : ORDER BY created_at
 *
 * Utilisation (depuis apply_patch ou manuel) :
 *   php migrate_perf_indexes.php [chemin_pharmacare]
 *
 * Idempotent : vérifie information_schema avant chaque ALTER. Ré-exécuter est sûr.
 * Lit les credentials BDD via la config de l'installation cible (env.php/env.prod.php),
 * donc aucun identifiant en dur.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script ne doit être exécuté qu'en ligne de commande (php CLI).\n");
    exit(1);
}

$live = $argv[1] ?? 'C:\\xampp\\htdocs\\pharmacare';
// Normalise les slashes Windows
$live = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $live);

$envFile = $live . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'env.php';
if (!file_exists($envFile)) {
    fwrite(STDERR, "Config introuvable : $envFile\n");
    fwrite(STDERR, "Passez le chemin du dossier pharmacare en argument.\n");
    exit(1);
}

// Charge la config BDD de l'install cible (définit DB_HOST/DB_NAME/DB_USER/DB_PASS/DB_CHARSET).
require $envFile;
$dbName = defined('DB_NAME') ? DB_NAME : 'pharmacare';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . $dbName . ';charset=' . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
try {
    $db = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Connexion BDD échouée : " . $e->getMessage() . "\n");
    exit(1);
}

// Index à créer : [table, nom_index, "colonnnes entre parenthèses", description humaine]
$indexes = [
    ['mouvements_stock',   'idx_msto_prod_type_date', '(produit_id, type, created_at)', 'MAX(created_at) par produit/type (stock.php)'],
    ['mouvements_magasin', 'idx_mmag_prod_type_date', '(produit_id, type, created_at)', 'agrégat mouvements magasin (etat-date)'],
    ['produits',           'idx_produits_actif_nom',   '(actif, nom)',                   'ORDER BY nom WHERE actif=1 (stock/magasin/produits)'],
    ['commandes',          'idx_cmd_created',          '(created_at)',                   'ORDER BY created_at (commandes.php)'],
    ['retours_vente',      'idx_retr_created',          '(created_at)',                   'ORDER BY created_at (retours.php)'],
];

echo "PharmaCare — migration des index de performance\n";
echo "Base : $dbName sur " . DB_HOST . "\n\n";

$created = 0;
$skipped = 0;
$failed  = 0;

$check = $db->prepare(
    "SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?"
);

foreach ($indexes as [$table, $name, $cols, $desc]) {
    // La table existe ?
    try {
        $db->query("SELECT 1 FROM `$table` LIMIT 1");
    } catch (PDOException $e) {
        echo "  [saute] table `$table` inexistante — $desc\n";
        $failed++;
        continue;
    }
    $check->execute([$table, $name]);
    if ((int)$check->fetchColumn() > 0) {
        echo "  [ok]    `$table`.`$name` déjà présent — $desc\n";
        $skipped++;
        continue;
    }
    try {
        $db->exec("ALTER TABLE `$table` ADD INDEX `$name` $cols");
        echo "  [ajout] `$table`.`$name` $cols créé — $desc\n";
        $created++;
    } catch (PDOException $e) {
        // Conflit de nom (index préexistant sous un autre nom, ou doublon) : on signale sans planter.
        fwrite(STDERR, "  [echec] `$table`.`$name` : " . $e->getMessage() . "\n");
        $failed++;
    }
}

echo "\nTerminé : $created créé(s), $skipped déjà présent(s), $failed échec(s).\n";
exit(0);