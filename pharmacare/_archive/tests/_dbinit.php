<?php
declare(strict_types=1);
/**
 * Initialisation de la BDD de test (pharmacare_test).
 *
 * Crée la base si absente, et importe le schéma de database.sql
 * (CREATE DATABASE / USE retirés, exécuté sur pharmacare_test).
 * Idempotent : ne recrée les tables que si la base est vide.
 */

function pharmacare_test_init_db(string $schemaFile): void {
    // Connexion serveur (sans base) pour créer pharmacare_test si besoin.
    $dsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
    try {
        $root = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $root->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        throw new RuntimeException(
            "Impossible de créer la base de test '" . DB_NAME . "' sur " . DB_HOST . ". " .
            "Vérifiez que MySQL/MariaDB est démarré et que les credentials (config/env.php, dev) sont corrects. " .
            "Cause : " . $e->getMessage()
        );
    }

    // Connexion à la base de test.
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Ne réimporter que si la base est vide (pas de table `parametres`).
    $hasTables = (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $db->quote(DB_NAME))->fetchColumn();
    if ($hasTables > 0) {
        return;
    }

    // Désactiver les checks FK pendant l'import (ordre de création des tables).
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');

    if (!is_file($schemaFile)) {
        throw new RuntimeException("Schéma introuvable : $schemaFile");
    }
    $sql = file_get_contents($schemaFile);

    // Retirer CREATE DATABASE / USE (on est déjà sur pharmacare_test).
    $sql = preg_replace('/^CREATE\s+DATABASE\s+.*?;/im', '', $sql);
    $sql = preg_replace('/^USE\s+`?\w+`?\s*;/im', '', $sql);

    // Splitter robuste sur les ; en fin de statement, en ignorant les commentaires --.
    // On procède ligne par ligne : on retire les commentaires, puis on recolle et on split sur ;.
    $lines = preg_split('/\r\n|\r|\n/', $sql);
    $cleaned = [];
    foreach ($lines as $line) {
        $t = ltrim($line);
        if ($t === '' || str_starts_with($t, '--') || str_starts_with($t, '/*')) {
            continue;
        }
        $cleaned[] = $line;
    }
    $clean = implode("\n", $cleaned);

    // Split sur ; (les seeds PharmaCare ne contiennent pas de ';' dans les valeurs).
    $statements = array_filter(array_map('trim', explode(';', $clean)));

    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        try {
            $db->exec($stmt);
        } catch (PDOException $e) {
            // Tolérant : on logge mais on ne stoppe pas (certains ordres peuvent échouer en idempotence).
            error_log('[pharmacare_test] SQL skip: ' . substr($stmt, 0, 80) . ' — ' . $e->getMessage());
        }
    }

    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}