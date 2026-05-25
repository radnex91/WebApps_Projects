<?php
// ButcheryPOS - Bootstrap: Autoloader, Session, DI, Settings
// This file is included by the front controller

// ============================================
// ERROR REPORTING
// ============================================
$config = require __DIR__ . '/config.php';

if ($config['app']['debug'] ?? false) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// ============================================
// TIMEZONE
// ============================================
date_default_timezone_set($config['app']['timezone'] ?? 'Africa/Douala');

// ============================================
// AUTOLOADER
// ============================================
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../src/';

    // Only handle App\ namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// ============================================
// SESSION
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// ============================================
// DATABASE
// ============================================
$pdo = App\Core\Database::getInstance($config['db']);

// ============================================
// HELPER FUNCTIONS
// ============================================
require __DIR__ . '/../src/Core/Helpers.php';

// ============================================
// INTERNATIONALIZATION
// ============================================
$langDir = __DIR__ . '/lang/';
$lang = $_SESSION['lang'] ?? ($config['default_language'] ?? 'fr');

// If user is logged in, use their preference
if (isset($_SESSION['user']['preferred_language'])) {
    $lang = $_SESSION['user']['preferred_language'];
}

// Validate language exists
if (!is_file($langDir . $lang . '.php')) {
    $lang = 'fr';
}

$_SESSION['lang'] = $lang;

$translations = [
    'en' => is_file($langDir . 'en.php') ? require $langDir . 'en.php' : [],
    'fr' => is_file($langDir . 'fr.php') ? require $langDir . 'fr.php' : [],
];

// ============================================
// PERMISSIONS MATRIX
// ============================================
$permStmt = $pdo->query("SELECT * FROM role_module_permissions");
$permRows = $permStmt ? $permStmt->fetchAll() : [];
App\Core\Permission::loadMatrix($permRows);

// ============================================
// APP SETTINGS
// ============================================
$settingsRows = $pdo->query("SELECT setting_key, setting_value, is_sensitive FROM app_settings")->fetchAll();
$appSettings = [];
foreach ($settingsRows as $row) {
    $appSettings[$row['setting_key']] = $row['setting_value'];
    $appSettings[$row['setting_key'] . '_is_sensitive'] = (bool)$row['is_sensitive'];
}

// ============================================
// CSRF
// ============================================
$csrfToken = App\Core\Csrf::getToken();

// ============================================
// EXPIRY ALERTS (lightweight session cache)
// ============================================
if (App\Core\Auth::check()) {
    // Refresh stale session data (role_name missing from older sessions)
    if (!isset($_SESSION['user']['role_name'])) {
        $stmt = $pdo->prepare(
            "SELECT r.role_name, r.display_name AS role_display
             FROM users u INNER JOIN app_roles r ON r.id = u.role_id
             WHERE u.id = :id"
        );
        $stmt->execute(['id' => $_SESSION['user']['id']]);
        $roleData = $stmt->fetch();
        if ($roleData) {
            $_SESSION['user']['role_name'] = $roleData['role_name'];
            $_SESSION['user']['role_display'] = $roleData['role_display'];
        }
    }

    $alertCount = $pdo->query(
        "SELECT COUNT(*) FROM expiry_alerts WHERE is_dismissed = 0 AND alert_type IN ('critical','expired')"
    )->fetchColumn();
    $_SESSION['expiry_alert_count'] = (int)$alertCount;
}