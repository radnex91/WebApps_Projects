<?php
declare(strict_types=1);
/**
 * PharmaCare — Migration offline-first : idempotence des ventes.
 *
 * Ajoute à `ventes` une clé d'idempotence générée par le terminal
 * (`client_ref`, UUID) avec index UNIQUE. Prérequis du mode hors ligne :
 * quand le serveur est redevenu joignable, la caisse rejoue les ventes
 * mises en file d'attente — si l'une d'elles avait déjà été enregistrée
 * (réponse perdue après commit), le rejeu renvoie la réception existante
 * au lieu de créer un doublon.
 *
 * Utilisation (depuis apply_patch / update_prod, ou manuel) :
 *   php migrate_ventes_offline_1.3.1.php [chemin_pharmacare]
 *
 * Idempotent : vérifie information_schema avant chaque ALTER. Ré-exécuter est sûr.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script ne doit être exécuté qu'en ligne de commande (php CLI).\n");
    exit(1);
}

$live = $argv[1] ?? 'C:\\xampp\\htdocs\\pharmacare';
$live = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, rtrim($live, '/\\'));

$envFile = $live . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'env.php';
if (!file_exists($envFile)) {
    fwrite(STDERR, "config/env.php introuvable sous : $live\n");
    exit(1);
}
require $envFile; // définit DB_* (env.prod.php chargé automatiquement si présent)

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

echo "PharmaCare — migration offline-first ventes (base : " . DB_NAME . ")\n";

$table = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'ventes'")->fetchColumn();
if ($table === 0) {
    fwrite(STDERR, "Table `ventes` absente — installation non initialisée ?\n");
    exit(1);
}

// ── 1) Colonne client_ref ───────────────────────────────────────────────────
$hasCol = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'ventes' AND column_name = 'client_ref'")->fetchColumn();
if ($hasCol === 0) {
    // MariaDB/XAMPP : ADD COLUMN IF NOT EXISTS supporté ; garde supplémentaire
    // via information_schema pour les serveurs qui ne le supportent pas.
    $pdo->exec("ALTER TABLE ventes ADD COLUMN client_ref VARCHAR(64) DEFAULT NULL");
    echo "  + colonne ventes.client_ref ajoutée (clé d'idempotence du terminal)\n";
} else {
    echo "  = colonne ventes.client_ref déjà présente\n";
}

// ── 2) Index UNIQUE (plusieurs NULL autorisés : les ventes anciennes restent NULL) ──
$hasIdx = (int)$pdo->query("SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'ventes' AND index_name = 'client_ref'")->fetchColumn();
if ($hasIdx === 0) {
    $pdo->exec("ALTER TABLE ventes ADD UNIQUE KEY client_ref (client_ref)");
    echo "  + index UNIQUE client_ref ajouté (garantie anti-doublon)\n";
} else {
    echo "  = index UNIQUE client_ref déjà présent\n";
}

echo "\nTerminé. Les ventes rejouées après une coupure serveur ne peuvent plus être dupliquées.\n";
exit(0);