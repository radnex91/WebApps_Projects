<?php
// ============================================================
// config.php - Configuration de l'application
// STOCK MOUTOURWA - MAROUA
// ============================================================

define('APP_NAME', 'StockMTW');
define('APP_TITLE', 'Gestion de Stock — MOUTOURWA');
define('APP_VERSION', '1.0.0');
define('CODE_PROJET', '30');
define('NOM_PROJET', 'MOUTOURWA - MAROUA');

// Base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'stock_moutourwa');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Session
define('SESSION_NAME', 'stock_moutourwa_sess');
define('SESSION_TIMEOUT', 3600 * 8); // 8 heures

// Timezone
date_default_timezone_set('Africa/Douala');

// Noms des mois en français
define('MOIS_FR', [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
]);

// Rôles et permissions
define('ROLES', [
    'admin'        => 'Administrateur',
    'gestionnaire' => 'Gestionnaire des Stocks',
    'assistant'    => 'Assistant Gestionnaire',
    'directeur'    => 'Directeur des Travaux',
    'raf'          => 'Responsable Administratif et Financier'
]);
