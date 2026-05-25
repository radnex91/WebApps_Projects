<?php require_once __DIR__ . '/../auth.php';
$current_page = $_GET['page'] ?? 'dashboard';
$user = currentUser();
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <span class="logo-icon">🚌</span>
            <span class="logo-text"><?= e(getSetting('app_short_name', 'DEX')) ?></span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle">☰</button>
    </div>

    <div class="sidebar-user">
        <div class="avatar"><?= e(strtoupper(substr($user['prenom'] ?? 'U', 0, 1) . substr($user['nom'] ?? '', 0, 1))) ?></div>
        <div class="user-info">
            <span class="user-name"><?= e($user['prenom'] ?? '') ?> <?= e($user['nom'] ?? '') ?></span>
            <span class="user-role"><?= e($user['role_nom'] ?? '') ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul>
            <li class="nav-section">Menu Principal</li>

            <li class="<?= $current_page === 'dashboard' ? 'active' : '' ?>">
                <a href="index.php?page=dashboard">
                    <span class="nav-icon animate-bounce">📊</span>
                    <span class="nav-text">Tableau de bord</span>
                </a>
            </li>

            <li class="nav-section">Gestion</li>

            <li class="<?= $current_page === 'bulletins' ? 'active' : '' ?>">
                <a href="index.php?page=bulletins">
                    <span class="nav-icon animate-pulse">📋</span>
                    <span class="nav-text">Bulletins</span>
                </a>
            </li>

            <li class="<?= $current_page === 'vehicules' ? 'active' : '' ?>">
                <a href="index.php?page=vehicules">
                    <span class="nav-icon animate-bounce">🚌</span>
                    <span class="nav-text">Véhicules</span>
                </a>
            </li>

            <li class="<?= $current_page === 'agences' ? 'active' : '' ?>">
                <a href="index.php?page=agences">
                    <span class="nav-icon animate-pulse">🏢</span>
                    <span class="nav-text">Agences</span>
                </a>
            </li>

            <li class="<?= $current_page === 'chauffeurs' ? 'active' : '' ?>">
                <a href="index.php?page=chauffeurs">
                    <span class="nav-icon animate-bounce">👨‍✈️</span>
                    <span class="nav-text">Chauffeurs</span>
                </a>
            </li>

            <li class="<?= $current_page === 'caisse' ? 'active' : '' ?>">
                <a href="index.php?page=caisse">
                    <span class="nav-icon animate-pulse">💰</span>
                    <span class="nav-text">Caisse</span>
                </a>
            </li>

            <li class="<?= $current_page === 'maintenances' ? 'active' : '' ?>">
                <a href="index.php?page=maintenances">
                    <span class="nav-icon animate-bounce">🔧</span>
                    <span class="nav-text">Maintenance</span>
                </a>
            </li>

            <?php if (hasPermission('users.view') || hasPermission('roles.view')): ?>
            <li class="nav-section">Administration</li>

            <?php if (hasPermission('users.view')): ?>
            <li class="<?= $current_page === 'users' ? 'active' : '' ?>">
                <a href="index.php?page=users">
                    <span class="nav-icon animate-pulse">👥</span>
                    <span class="nav-text">Utilisateurs</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('roles.view')): ?>
            <li class="<?= $current_page === 'roles' ? 'active' : '' ?>">
                <a href="index.php?page=roles">
                    <span class="nav-icon animate-bounce">🔐</span>
                    <span class="nav-text">Rôles</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('settings.view')): ?>
            <li class="<?= $current_page === 'settings' ? 'active' : '' ?>">
                <a href="index.php?page=settings">
                    <span class="nav-icon animate-pulse">⚙️</span>
                    <span class="nav-text">Paramètres</span>
                </a>
            </li>
            <?php endif; ?>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <a href="index.php?page=logout" class="btn-logout">
            <span class="nav-icon">🚪</span>
            <span class="nav-text">Déconnexion</span>
        </a>
    </div>
</aside>
