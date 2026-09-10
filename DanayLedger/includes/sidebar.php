<?php
$role = $_SESSION['user_role'] ?? '';
$perms = $_SESSION['user_permissions'] ?? [];
$userInitials = strtoupper(mb_substr($_SESSION['full_name'] ?? 'U', 0, 1));
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Navigation principale">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon"><i class="bi bi-cash-stack"></i></div>
        <div class="sidebar-brand-text">
            DanayLedger
            <small>Gestion Financière</small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section" role="separator">Principal</div>

        <?php if (in_array('dashboard', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/dashboard.php">
                <i class="bi bi-speedometer2"></i> Tableau de bord
            </a>
        </div>
        <?php endif; ?>

        <div class="nav-section" role="separator">Finances</div>

        <?php if (in_array('recettes', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'recettes' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/recettes/">
                <i class="bi bi-cash-coin"></i> Recettes journalières
            </a>
        </div>
        <?php endif; ?>

        <?php if (in_array('depenses', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'depenses' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/depenses/">
                <i class="bi bi-receipt"></i> Dépenses
            </a>
        </div>
        <?php endif; ?>

        <?php if (in_array('recettes_camions', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'recettes-camions' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/recettes-camions/">
                <i class="bi bi-truck"></i> Recettes camions
            </a>
        </div>
        <?php endif; ?>

        <?php if (in_array('versements', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'versements' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/versements/">
                <i class="bi bi-bank2"></i> Versements bancaires
            </a>
        </div>
        <?php endif; ?>

        <div class="nav-section" role="separator">Analyses</div>

        <?php if (in_array('recherche', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'recherche' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/recherche/">
                <i class="bi bi-search"></i> Recherche
            </a>
        </div>
        <?php endif; ?>

        <?php if (in_array('rapports', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'rapports' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/rapports/">
                <i class="bi bi-bar-chart-line"></i> Rapports
            </a>
        </div>
        <?php endif; ?>

        <?php if (in_array('rapprochement', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'rapprochement' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/rapprochement/">
                <i class="bi bi-balanced-scale"></i> Rapprochement
            </a>
        </div>
        <?php endif; ?>

        <div class="nav-section" role="separator">Outils</div>

        <?php if (in_array('imports', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'imports' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/imports/">
                <i class="bi bi-box-arrow-in-down"></i> Importation
            </a>
        </div>
        <?php endif; ?>

        <?php if (in_array('exports', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'exports' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/exports/">
                <i class="bi bi-box-arrow-up"></i> Exportation
            </a>
        </div>
        <?php endif; ?>

        <?php if (in_array('audit', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'audit' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/audit/">
                <i class="bi bi-journal-text"></i> Journal d'audit
            </a>
        </div>
        <?php endif; ?>

        <?php if (in_array('sauvegarde', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'sauvegarde' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/sauvegarde/">
                <i class="bi bi-download"></i> Sauvegarde
            </a>
        </div>
        <?php endif; ?>

        <div class="nav-section" role="separator">Paramètres</div>

        <?php if (in_array('settings', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'settings' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/settings/">
                <i class="bi bi-gear"></i> Paramétrage
            </a>
        </div>
        <?php endif; ?>

        <?php if (in_array('settings_banks', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'banks' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/settings/banks.php">
                <i class="bi bi-bank"></i> Banques
            </a>
        </div>
        <?php endif; ?>

        <?php if (in_array('settings_proprietaires', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'proprietaires' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/settings/proprietaires.php">
                <i class="bi bi-people"></i> Propriétaires
            </a>
        </div>
        <?php endif; ?>

        <?php if (in_array('settings_repartition', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'repartition' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/settings/repartition.php">
                <i class="bi bi-pie-chart"></i> Répartition
            </a>
        </div>
        <?php endif; ?>

        <?php if (in_array('users', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'users' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/users/">
                <i class="bi bi-person-gear"></i> Utilisateurs
            </a>
        </div>
        <?php endif; ?>

        <?php if (in_array('settings_roles', $perms)): ?>
        <div class="nav-item">
            <a class="nav-link <?php echo $activePage === 'settings' && basename($_SERVER['PHP_SELF']) === 'roles.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/settings/roles.php">
                <i class="bi bi-shield-lock"></i> Types d'utilisateurs
            </a>
        </div>
        <?php endif; ?>
    </nav>

    <!-- Profil utilisateur en bas de sidebar -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="dropdown">
                <div class="sidebar-user-btn" data-bs-toggle="dropdown" aria-expanded="false" role="button" tabindex="0" aria-label="Menu utilisateur">
                    <div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div>
                    <div class="user-info">
                        <div class="user-name"><?php echo e($_SESSION['full_name'] ?? 'Utilisateur'); ?></div>
                        <div class="user-role"><?php echo e($roleLabel ?? 'Agent'); ?></div>
                    </div>
                </div>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a>
                </div>
            </div>
        </div>
    </div>
</aside>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>