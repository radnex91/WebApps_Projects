<?php
define('DB_HOST', getenv('RENTFLOW_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('RENTFLOW_DB_NAME') ?: 'rentflow');
define('DB_USER', getenv('RENTFLOW_DB_USER') ?: 'root');
define('DB_PASS', getenv('RENTFLOW_DB_PASS') ?: '');
define('DB_CHARSET', getenv('RENTFLOW_DB_CHARSET') ?: 'utf8mb4');

// Auto-detect BASE_URL (works on localhost + LAN)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', getenv('RENTFLOW_BASE_URL') ?: ($scheme . '://' . $host . '/RentFlow/public'));
define('APP_NAME', 'RentFlow - Gestion Immobilière');
