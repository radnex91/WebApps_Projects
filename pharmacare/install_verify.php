<?php
/**
 * Vérification d'installation — utilisé par install_local.bat (CLI).
 * Teste la connexion PHP -> MySQL et compte les tables/utilisateurs/produits.
 * Usage : php install_verify.php
 * N'expose rien de sensible (comptages uniquement).
 */
require __DIR__ . '/config/env.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/config/settings.php';
require __DIR__ . '/includes/auth.php';

try {
    $db = getDB();
    $n = (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $db->quote(DB_NAME))->fetchColumn();
    $u = (int)$db->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
    $p = (int)$db->query("SELECT COUNT(*) FROM produits WHERE actif=1")->fetchColumn();
    echo "TABLES=$n USERS=$u PRODUITS=$p";
    exit(0);
} catch (Throwable $e) {
    echo "FAIL: " . $e->getMessage();
    exit(1);
}