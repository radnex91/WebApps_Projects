<?php
$page = $_GET['page'] ?? 'dashboard';

if ($page === 'logout') {
    session_start();
    session_destroy();
    header('Location: index.php?page=login');
    exit;
}

$allowed_pages = ['dashboard', 'bailleurs', 'agences', 'lots', 'paiements', 'utilisateurs', 'parametres', 'rapports', 'statistiques', 'rappels', 'login', 'logout'];

if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}

if ($page === 'login' || $page === 'logout') {
    include 'pages/' . $page . '.php';
    exit;
}

require_once 'includes/db.php';
include 'includes/header.php';

if (file_exists('pages/' . $page . '.php')) {
    include 'pages/' . $page . '.php';
} else {
    echo '<div class="alert alert-danger">Page non trouvee.</div>';
}

include 'includes/footer.php';