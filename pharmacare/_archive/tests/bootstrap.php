<?php
declare(strict_types=1);
/**
 * Bootstrap PHPUnit — PharmaCare
 *
 * - Force l'environnement de test (PHARMACARE_ENV=test → BDD pharmacare_test).
 * - Initialise une session factice (les tests CSRF ne dépendent pas du navigateur).
 * - Crée/importe le schéma de la BDD de test une fois par exécution.
 *
 * Prérequis : `composer install` (phpunit) + MySQL/MariaDB local accessible
 * avec les credentials de config/env.php (dev : root / mot de passe vide).
 */

// 1) Forcer l'environnement de test AVANT tout chargement de config.
putenv('PHARMACARE_ENV=test');
$_ENV['PHARMACARE_ENV'] = 'test';

// 2) Session factice pour verifyCsrf() / csrf() (CLI-safe).
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
    if (!isset($_SESSION)) {
        $_SESSION = [];
    }
}
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// 3) Charger la config + helpers (env.php détecte PHARMACARE_ENV=test → pharmacare_test).
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/comptabilite.php';
require_once __DIR__ . '/../includes/auth.php';

// 4) Initialiser la BDD de test (idempotent : crée le schéma si la base est vide).
require_once __DIR__ . '/_dbinit.php';
pharmacare_test_init_db(__DIR__ . '/../database.sql');