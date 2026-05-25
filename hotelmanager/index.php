<?php
/**
 * HotelPro Suite - Point d'entrée unique (Front Controller)
 */

// ── Démarrage de session sécurisée ───────────────────────────
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'gc_maxlifetime'  => 3600,
]);

// ── Chargement de la configuration ──────────────────────────
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// ── Autoloader simple ────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $paths = [
        __DIR__ . '/app/controllers/' . $class . '.php',
        __DIR__ . '/app/models/'      . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// ── Helpers globaux ─────────────────────────────────────────
require_once __DIR__ . '/app/helpers.php';

// ── Routeur ──────────────────────────────────────────────────
$page   = $_GET['page']   ?? 'dashboard';
$action = $_GET['action'] ?? 'index';

// Pages accessibles sans authentification
$public_pages = ['login', 'logout'];

// Vérification de l'authentification
if (!in_array($page, $public_pages) && !isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php?page=login');
    exit;
}

// Vérification du timeout de session
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Votre session a expiré. Veuillez vous reconnecter.'];
    header('Location: ' . APP_URL . '/index.php?page=login');
    exit;
}
$_SESSION['last_activity'] = time();

// Table de routage
$routes = [
    'login'        => ['AuthController',        'login'],
    'logout'       => ['AuthController',        'logout'],
    'dashboard'    => ['DashboardController',   'index'],
    'reservations' => ['ReservationController', $action],
    'chambres'     => ['ChambreController',     $action],
    'clients'      => ['ClientController',      $action],
    'facturation'  => ['FacturationController', $action],
    'paiements'    => ['PaiementController',    $action],
    'personnel'    => ['PersonnelController',   $action],
    'rapports'     => ['RapportController',     $action],
    'profil'       => ['ProfilController',      $action],
];

if (isset($routes[$page])) {
    [$controllerClass, $method] = $routes[$page];

    if (class_exists($controllerClass)) {
        $controller = new $controllerClass();

        if (method_exists($controller, $method)) {
            $controller->$method();
        } else {
            // Méthode par défaut si action inconnue
            $controller->index();
        }
    } else {
        render_error(404, "Contrôleur introuvable : $controllerClass");
    }
} else {
    render_error(404, "Page introuvable : $page");
}

/**
 * Affiche une page d'erreur générique
 */
function render_error(int $code, string $message): void
{
    http_response_code($code);
    require APP_ROOT . '/app/views/layouts/header.php';
    echo "<div class='alert alert-danger m-4'>Erreur $code : " . htmlspecialchars($message) . "</div>";
    require APP_ROOT . '/app/views/layouts/footer.php';
}
