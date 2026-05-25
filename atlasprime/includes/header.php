<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
Auth::check();
$user = Auth::currentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle ?? APP_NAME) ?> — Atlas Prime Logistics</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= APP_ROOT ?>assets/css/main.css">
    
    <?= $extraCSS ?? '' ?>
</head>
<body>
<!-- SIDEBAR -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon"><i class="fas fa-truck-fast"></i></div>
        <div class="logo-text">
            <span class="logo-name">Atlas Prime</span>
            <span class="logo-sub">Logistics</span>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($user['nom'], 0, 1)) ?></div>
        <div class="user-info">
            <span class="user-name"><?= sanitize($user['nom']) ?></span>
            <span class="user-role"><?= ucfirst($user['role']) ?></span>
        </div>
    </div>

    <ul class="nav-menu">
        <li class="nav-section">Principal</li>
        <li class="<?= $currentPage === 'index' ? 'active' : '' ?>">
            <a href="<?= APP_ROOT ?>index.php"><i class="fas fa-gauge-high"></i> Tableau de bord</a>
        </li>

        <li class="nav-section">Expéditions</li>
        <li class="<?= $currentPage === 'colis' ? 'active' : '' ?>">
            <a href="<?= APP_ROOT ?>pages/colis.php"><i class="fas fa-paper-plane"></i> Expéditions</a>
        </li>
        <?php if (Auth::hasPermission('colis_voir')): ?>
        <li class="<?= $currentPage === 'suivi' ? 'active' : '' ?>">
            <a href="<?= APP_ROOT ?>pages/suivi.php"><i class="fas fa-map-location-dot"></i> Suivi colis</a>
        </li>
        <?php endif; ?>

        <?php if (Auth::hasPermission('manifest_gerer')): ?>
        <li class="<?= $currentPage === 'manifest' ? 'active' : '' ?>">
            <a href="<?= APP_ROOT ?>pages/manifest.php"><i class="fas fa-truck-loading"></i> Nouveau manifest</a>
        </li>
        <li class="<?= $currentPage === 'manifest_complement' ? 'active' : '' ?>">
            <a href="<?= APP_ROOT ?>pages/manifest_complement.php"><i class="fas fa-boxes-stacked"></i> Manifest complément</a>
        </li>
        <?php endif; ?>

        <?php if (Auth::hasPermission('rapports_voir') || Auth::hasPermission('bordereaux_imprimer')): ?>
        <li class="nav-section">Rapports</li>
        <?php if (Auth::hasPermission('rapports_voir')): ?>
        <li class="<?= $currentPage === 'rapports' ? 'active' : '' ?>">
            <a href="<?= APP_ROOT ?>pages/rapports.php"><i class="fas fa-chart-bar"></i> Rapports</a>
        </li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('bordereaux_imprimer')): ?>
        <li class="<?= $currentPage === 'bordereaux' ? 'active' : '' ?>">
            <a href="<?= APP_ROOT ?>pages/bordereaux.php"><i class="fas fa-file-invoice"></i> Bordereaux</a>
        </li>
<?php endif; ?>
<?php endif; ?>

        <?php
        $showAdmin = Auth::hasPermission('agences_gerer') || Auth::hasPermission('vehicules_gerer')
            || Auth::hasPermission('trajets_gerer') || Auth::hasPermission('utilisateurs_gerer')
            || Auth::hasPermission('parametres');
        ?>
        <?php if ($showAdmin): ?>
        <li class="nav-section">Administration</li>
        <?php if (Auth::hasPermission('agences_gerer')): ?>
        <li class="<?= $currentPage === 'agences' ? 'active' : '' ?>">
            <a href="<?= APP_ROOT ?>pages/agences.php"><i class="fas fa-building"></i> Agences</a>
        </li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('vehicules_gerer')): ?>
        <li class="<?= $currentPage === 'vehicules' ? 'active' : '' ?>">
            <a href="<?= APP_ROOT ?>pages/vehicules.php"><i class="fas fa-truck"></i> Véhicules</a>
        </li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('trajets_gerer')): ?>
        <li class="<?= $currentPage === 'trajets' ? 'active' : '' ?>">
            <a href="<?= APP_ROOT ?>pages/trajets.php"><i class="fas fa-route"></i> Trajets</a>
        </li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('utilisateurs_gerer')): ?>
        <li class="<?= $currentPage === 'utilisateurs' ? 'active' : '' ?>">
            <a href="<?= APP_ROOT ?>pages/utilisateurs.php"><i class="fas fa-users"></i> Utilisateurs</a>
        </li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('parametres')): ?>
        <li class="<?= $currentPage === 'parametres' ? 'active' : '' ?>">
            <a href="<?= APP_ROOT ?>pages/parametres.php"><i class="fas fa-cog"></i> Paramètres</a>
        </li>
        <li class="<?= $currentPage === 'roles' ? 'active' : '' ?>">
            <a href="<?= APP_ROOT ?>pages/roles.php"><i class="fas fa-shield-halved"></i> Rôles</a>
        </li>
        <?php endif; ?>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
        <a href="<?= APP_ROOT ?>logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
    </div>
</nav>

<!-- MAIN CONTENT -->
<div class="main-wrapper">
    <!-- TOP BAR -->
    <header class="topbar">
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-title"><?= sanitize($pageTitle ?? 'Tableau de bord') ?></div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="far fa-calendar"></i> <?= date('d/m/Y') ?> <span id="currentTime"><i class="far fa-clock"></i> <?= date('H:i') ?></span></div>
            
        </div>
    </header>

    <!-- FLASH MESSAGES -->
    <?php if (isset($_SESSION['flash'])): ?>
    <div class="flash flash-<?= $_SESSION['flash']['type'] ?>">
        <i class="fas fa-<?= $_SESSION['flash']['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
        <?= sanitize($_SESSION['flash']['message']) ?>
        <button onclick="this.parentElement.remove()" class="flash-close">&times;</button>
    </div>
    <?php unset($_SESSION['flash']); endif; ?>

    <main class="content">
