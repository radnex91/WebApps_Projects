<?php
define('APP_NAME', 'AureliaHost');
define('BASE_URL', 'http://localhost/AureliaHost');
define('ROOT_DIR', dirname(__DIR__, 2));
define('APP_DIR', ROOT_DIR . '/app');
define('VIEWS_DIR', APP_DIR . '/views');
define('TIMEZONE', 'Africa/Casablanca');
define('LOCALE', 'fr_FR');
define('SESSION_LIFETIME', 86400);

date_default_timezone_set(TIMEZONE);
setlocale(LC_TIME, LOCALE . '.UTF-8');
