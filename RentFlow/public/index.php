<?php
// Detect environment: set RENTFLOW_ENV=production on your server
$env = getenv('RENTFLOW_ENV') ?: 'development';
if ($env === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
}

header('Content-Type: text/html; charset=UTF-8');

// Secure session cookies before starting session
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Csrf.php';
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/PermissionMiddleware.php';

// Apply timezone from settings
applyTimezone();

require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Landlord.php';
require_once __DIR__ . '/../app/models/Agency.php';
require_once __DIR__ . '/../app/models/Batch.php';
require_once __DIR__ . '/../app/models/Payment.php';
require_once __DIR__ . '/../app/models/Reminder.php';
require_once __DIR__ . '/../app/models/Settings.php';
require_once __DIR__ . '/../app/models/Role.php';
require_once __DIR__ . '/../app/models/Permission.php';

require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/controllers/DashboardController.php';
require_once __DIR__ . '/../app/controllers/LandlordController.php';
require_once __DIR__ . '/../app/controllers/AgencyController.php';
require_once __DIR__ . '/../app/controllers/BatchController.php';
require_once __DIR__ . '/../app/controllers/PaymentController.php';
require_once __DIR__ . '/../app/controllers/UserController.php';

$database = Database::getInstance();

$userModel = new User();
$userModel->createDefaultUser();

$router = require_once __DIR__ . '/../routes/web.php';
$router->dispatch();
