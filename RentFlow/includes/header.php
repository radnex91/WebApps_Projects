<?php
session_start();
require_once 'includes/db.php';
$current_page = $_GET['page'] ?? 'dashboard';

$is_logged = isset($_SESSION['user_id']);
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if (!$is_logged && $current_page !== 'login') {
    header('Location: index.php?page=login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getParam('nom_entreprise', 'RentFlow'); ?> - Gestion des Loyers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <?php
    $police = getParam('police', 'system');
    if ($police !== 'system') {
        echo '<link href="https://fonts.googleapis.com/css2?family=' . str_replace('-', '+', $police) . ':wght@400;500;600;700&display=swap" rel="stylesheet">';
    }
    ?>
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --theme-color: <?php echo getThemeColor(); ?>;
            --theme-rgb: <?php echo getThemeRGB(); ?>;
            <?php if ($police !== 'system'): ?>
            --font-family: '<?php echo ucfirst($police); ?>', sans-serif;
            <?php endif; ?>
        }
    </style>
</head>
<body>
<div class="wrapper">
    <nav class="sidebar">
        <div class="brand">
            <span>🏠 RentFlow</span>
        </div>
        <ul class="nav-menu">
            <li>
                <a href="dashboard" class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                    <span class="emoji">📊</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="bailleurs" class="nav-link <?php echo $current_page === 'bailleurs' ? 'active' : ''; ?>">
                    <span class="emoji">👤</span>
                    <span>Bailleurs</span>
                </a>
            </li>
            <li>
                <a href="agences" class="nav-link <?php echo $current_page === 'agences' ? 'active' : ''; ?>">
                    <span class="emoji">🏢</span>
                    <span>Agences</span>
                </a>
            </li>
            <li>
                <a href="lots" class="nav-link <?php echo $current_page === 'lots' ? 'active' : ''; ?>">
                    <span class="emoji">🚪</span>
                    <span>Lots</span>
                </a>
            </li>
            <li>
                <a href="paiements" class="nav-link <?php echo $current_page === 'paiements' ? 'active' : ''; ?>">
                    <span class="emoji">💰</span>
                    <span>Paiements</span>
                </a>
            </li>
            <li>
                <a href="rappels" class="nav-link <?php echo $current_page === 'rappels' ? 'active' : ''; ?>">
                    <span class="emoji">🔔</span>
                    <span>Rappels</span>
                </a>
            </li>
            <li>
                <a href="rapports" class="nav-link <?php echo $current_page === 'rapports' ? 'active' : ''; ?>">
                    <span class="emoji">📄</span>
                    <span>Rapports</span>
                </a>
            </li>
            <li>
                <a href="statistiques" class="nav-link <?php echo $current_page === 'statistiques' ? 'active' : ''; ?>">
                    <span class="emoji">📈</span>
                    <span>Statistiques</span>
                </a>
            </li>
            <?php if ($is_admin): ?>
            <li>
                <a href="utilisateurs" class="nav-link <?php echo $current_page === 'utilisateurs' ? 'active' : ''; ?>">
                    <span class="emoji">⚙️</span>
                    <span>Utilisateurs</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        <div class="sidebar-footer">
            <?php if ($is_logged): ?>
            <div class="user-info">
                <span>👤</span>
                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
            <a href="index.php?page=logout" class="logout-link">
                <span>🚪</span> <span>Déconnexion</span>
            </a>
            <?php endif; ?>
            <small>&copy; <?php echo date('Y'); ?> RentFlow</small>
        </div>
    </nav>
    <div class="main-content">
        <header class="topbar">
            <button class="btn-toggle" id="sidebarToggle">
                <span>☰</span>
            </button>
            <h1 class="page-title">Gestion des Loyers</h1>
        </header>
        <main class="container">