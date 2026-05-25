<?php
// ============================================================
// config/config.php - Configuration principale
// ============================================================

define('APP_NAME', 'POS System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/Brenshop');
define('ROOT_PATH', dirname(__DIR__));

// Base de données
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'pos_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Session
define('SESSION_LIFETIME', 3600 * 8); // 8 heures
define('SESSION_NAME', 'POS_SESSION');

// Upload
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads/');
define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// Pagination
define('ITEMS_PER_PAGE', 25);

// Timezone
date_default_timezone_set('Africa/Douala');
