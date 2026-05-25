<?php
ob_start();
// Simple Router
$page = $_GET['page'] ?? 'login';

// Whitelist of allowed pages
$allowed_pages = [
    'login', 'logout', 'dashboard',
    'users', 'roles', 'settings',
    'vehicules', 'agences', 'chauffeurs',
    'bulletins', 'caisse', 'maintenances',
];

require_once __DIR__ . '/includes/auth.php';

// Special handling for login/logout
if ($page === 'login') {
    require __DIR__ . '/modules/login.php';
    exit;
}

if ($page === 'logout') {
    logout();
    exit;
}

// All other pages require login
requireLogin();

if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}

$page_title = match($page) {
    'dashboard' => 'Tableau de bord',
    'users' => 'Gestion des utilisateurs',
    'roles' => 'Gestion des rôles',
    'settings' => 'Paramètres',
    'vehicules' => 'Gestion des véhicules',
    'agences' => 'Gestion des agences',
    'chauffeurs' => 'Gestion des chauffeurs',
    'bulletins' => 'Bulletins d\'exploitation',
    'caisse' => 'Gestion de caisse',
    'maintenances' => 'Maintenance IT',
    default => 'DEX Transport',
};

include __DIR__ . '/includes/layout/header.php';

$module_path = __DIR__ . '/modules/' . $page . '.php';
if (file_exists($module_path)) {
    require $module_path;
} else {
    echo '<div class="alert alert-danger">Module introuvable.</div>';
}

include __DIR__ . '/includes/layout/footer.php';
ob_end_flush();
