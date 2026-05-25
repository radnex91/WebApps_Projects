<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/systraco');
}
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__ . '/..');
}
define('PUBLIC_PATH', __DIR__ . '/../public');

function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit;
}

function auth() {
    if (!isset($_SESSION['user_id'])) {
        redirect('/login');
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function getAgenceId() {
    return $_SESSION['agence_id'] ?? null;
}

function flash($message, $type = 'info') {
    $_SESSION['flash'][$type] = $message;
}

function getFlash() {
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

function assets($path) {
    return BASE_URL . '/' . ltrim($path, '/');
}

function view($view, $data = []) {
    extract($data);
    $viewFile = __DIR__ . '/../views/' . $view . '.php';
    if (!file_exists($viewFile)) {
        die("Vue non trouvée: " . $view);
    }
    require $viewFile;
}