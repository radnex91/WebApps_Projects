<?php
/**
 * HotelPro Suite - Configuration générale
 */

// ── Environnement ────────────────────────────────────────────
define('APP_NAME',    'HotelPro Suite');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/hotelmanager');
define('APP_ROOT',    dirname(__DIR__));

// ── Base de données ──────────────────────────────────────────
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'hotelmanager');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ── Hôtel ────────────────────────────────────────────────────
define('HOTEL_NOM',       'Grand Hôtel Central');
define('HOTEL_ADRESSE',   'Avenue Kennedy, Yaoundé, Cameroun');
define('HOTEL_TEL',       '+237 222 000 001');
define('HOTEL_EMAIL',     'contact@grandhotelcentral.cm');
define('HOTEL_TVA',       19.25); // Taux TVA Cameroun
define('HOTEL_DEVISE',    'FCFA');
define('HOTEL_NBRE_CHAMBRES', 55);

// ── Sécurité ─────────────────────────────────────────────────
define('SESSION_LIFETIME', 3600);   // 1 heure
define('BCRYPT_COST',      12);
define('CSRF_TOKEN_NAME',  '_csrf_token');

// ── Pagination ───────────────────────────────────────────────
define('ITEMS_PER_PAGE', 15);

// ── Chemins ──────────────────────────────────────────────────
define('UPLOAD_PATH', APP_ROOT . '/uploads/');
define('LOG_PATH',    APP_ROOT . '/logs/');

// ── Timezone ────────────────────────────────────────────────
date_default_timezone_set('Africa/Douala');

// ── Gestion d'erreurs ────────────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', LOG_PATH . 'php_errors.log');
