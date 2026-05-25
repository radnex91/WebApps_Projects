<?php
ob_start();
// ============================================================
// ATLAS PRIME LOGISTICS - Configuration
// ============================================================

define('APP_NAME', 'Atlas Prime Logistics');
define('APP_VERSION', '1.0.0');
define('APP_SLOGAN', 'Votre colis, notre priorité');

// Base de données
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'atlas_prime_logistics');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Sécurité session
define('SESSION_LIFETIME', 3600 * 8); // 8 heures
define('SESSION_NAME', 'ATLAS_PRIME_SESSION');

// Chemins - calculer le chemin relatif depuis le fichier inclus jusqu'à la racine du projet
// Pour être utilisé dans les fichiers inclus depuis différents niveaux
$projectRoot = realpath(dirname(__DIR__)); // Chemin absolu du dossier du projet (atlas_prime)
if ($projectRoot === false) {
    $projectRoot = dirname(__DIR__);
}
// Normaliser les séparateurs
$projectRoot = str_replace('\\', '/', $projectRoot);
// Le répertoire du fichier appelant (celui qui inclut ce fichier)
$callingFile = $_SERVER['SCRIPT_FILENAME'] ?? __FILE__;
$callingDir = dirname($callingFile);
$callingDir = str_replace('\\', '/', $callingDir);
// Calculer le chemin relatif depuis le répertoire du projet jusqu'au répertoire du fichier appelant
$relativePath = '';
if (strpos($callingDir, $projectRoot) === 0) {
    $relativePath = substr($callingDir, strlen($projectRoot));
}
// Supprimer le slash initial s'il existe
if ($relativePath !== '' && $relativePath[0] === '/') {
    $relativePath = substr($relativePath, 1);
}
// Calculer combien de niveaux nous devons remonter pour atteindre la racine du projet
// Si relativePath est vide, nous sommes à la racine → 0 niveaux à remonter
// Sinon, le nombre de niveaux est égal au nombre de séparateurs '/' plus 1
$levelsUp = $relativePath === '' ? 0 : (substr_count($relativePath, '/') + 1);
// Construire le chemin relatif pour remonter à la racine du projet
$appRoot = $levelsUp > 0 ? str_repeat('../', $levelsUp) : '';
define('APP_ROOT', $appRoot);
define('ROOT_PATH', $projectRoot);
define('INCLUDES_PATH', ROOT_PATH . '/includes');

// Fuseau horaire
date_default_timezone_set('Africa/Douala');

// API Externe (voyage)
define('EXTERNAL_API_URL', '');  // URL de l'API externe
define('EXTERNAL_API_KEY', '');   // Clé API
