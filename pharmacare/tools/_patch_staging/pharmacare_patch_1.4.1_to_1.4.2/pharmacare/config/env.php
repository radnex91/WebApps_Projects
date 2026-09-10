<?php
declare(strict_types=1);
/**
 * Détection de l'environnement et configuration associée.
 * Chargé AVANT database.php et auth.php.
 *
 * Pour passer en production, créer un fichier config/env.prod.php
 * ou définir la variable d'environnement PHARMACARE_ENV=prod.
 */

// ── Sécurité : affichage d'erreurs (au plus tôt, avant tout point d'entrée) ──
$envDetected = getenv('PHARMACARE_ENV') ?: 'dev';
$hasEnvProd = file_exists(__DIR__ . '/env.prod.php');
// Filet de sécurité : si l'app est déployée sans env.prod.php ni PHARMACARE_ENV=prod
// mais sur un hôte qui n'est PAS localhost, on est probablement en prod oubliée —
// on force l'absence d'affichage d'erreurs pour ne pas fuiter d'internals.
$hostIsLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1'], true)
            && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
if ($envDetected === 'prod' || $hasEnvProd) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
} elseif (!$hostIsLocal) {
    // Prod oubliée : on logge, on n'affiche rien.
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}

// ── Détection environnement ─────────────────────────────────
$env = getenv('PHARMACARE_ENV') ?: 'dev';

// Override si un fichier env.prod.php existe (prioritaire)
if (file_exists(__DIR__ . '/env.prod.php')) {
    $env = 'prod';
}

define('APP_ENV', $env);
define('IS_PROD', $env === 'prod');
define('IS_TEST', $env === 'test');

// ── Configuration BDD par environnement ────────────────────
if (IS_PROD) {
    $prodConfig = require __DIR__ . '/env.prod.php';
    define('DB_HOST', $prodConfig['DB_HOST']);
    define('DB_NAME', $prodConfig['DB_NAME']);
    define('DB_USER', $prodConfig['DB_USER']);
    define('DB_PASS', $prodConfig['DB_PASS']);
    define('APP_URL', $prodConfig['APP_URL']);
} elseif (IS_TEST) {
    // BDD de test (pharmacare_test) — activée uniquement via PHARMACARE_ENV=test
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'pharmacare_test');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('APP_URL', 'http://localhost/pharmacare');
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'pharmacare');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('APP_URL', 'http://localhost/pharmacare');
}

// ── Constantes partagées ───────────────────────────────────
define('DB_CHARSET', 'utf8mb4');
define('APP_NAME', 'PharmaCare');
define('APP_VERSION', '1.4.2');
define('SESSION_NAME', 'pharmacare_session');

// ── Gestionnaire d'erreurs fatales + buffer de sortie (bootstrap) ──
// Doit être chargé après définition de IS_PROD. Inactif en CLI.
require_once __DIR__ . '/../includes/bootstrap_errors.php';
