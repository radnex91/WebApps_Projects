<!DOCTYPE html>
<html lang="fr" data-theme="<?= theme() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'AureliaHost') ?> — AureliaHost</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/themes.css') ?>" rel="stylesheet">
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <aside class="app-sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="<?= url('') ?>" class="sidebar-brand">
                <div class="sidebar-logo">
                    <i class="bi bi-building"></i>
                </div>
                <div class="sidebar-brand-text">
                    <span class="sidebar-brand-name">AureliaHost</span>
                    <span class="sidebar-brand-sub">Management</span>
                </div>
            </a>
            <button class="sidebar-toggle" id="sidebarToggle" title="Réduire">
                <i class="bi bi-list"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <!-- Dashboard -->
            <a href="<?= url('') ?>" class="sidebar-link <?= activeLink('') ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            <?php if (in_array(Session::userRole(), ['admin', 'receptionist', 'manager'])): ?>
            <!-- Hôtellerie -->
            <div class="sidebar-section-label"><span>Hôtellerie</span></div>
            <a href="<?= url('hotel/reservations') ?>" class="sidebar-link <?= activeLink('hotel/reservations') ?>">
                <i class="bi bi-calendar-check"></i><span>Réservations</span>
            </a>
            <a href="<?= url('hotel/rooms') ?>" class="sidebar-link <?= activeLink('hotel/rooms') ?>">
                <i class="bi bi-door-open"></i><span>Chambres</span>
            </a>
            <a href="<?= url('hotel/room-types') ?>" class="sidebar-link <?= activeLink('hotel/room-types') ?>">
                <i class="bi bi-tags"></i><span>Types de chambres</span>
            </a>
            <a href="<?= url('hotel/clients') ?>" class="sidebar-link <?= activeLink('hotel/clients') ?>">
                <i class="bi bi-people"></i><span>Clients</span>
            </a>
            <a href="<?= url('hotel/services') ?>" class="sidebar-link <?= activeLink('hotel/services') ?>">
                <i class="bi bi-cup-hot"></i><span>Services</span>
            </a>
            <a href="<?= url('hotel/housekeeping') ?>" class="sidebar-link <?= activeLink('hotel/housekeeping') ?>">
                <i class="bi bi-droplet"></i><span>Entretien</span>
            </a>
            <?php endif; ?>

            <?php if (in_array(Session::userRole(), ['admin', 'hr', 'manager'])): ?>
            <!-- RH -->
            <div class="sidebar-section-label"><span>Ressources Humaines</span></div>
            <a href="<?= url('hr/employees') ?>" class="sidebar-link <?= activeLink('hr/employees') ?>">
                <i class="bi bi-person-badge"></i><span>Employés</span>
            </a>
            <a href="<?= url('hr/departments') ?>" class="sidebar-link <?= activeLink('hr/departments') ?>">
                <i class="bi bi-diagram-3"></i><span>Départements</span>
            </a>
            <a href="<?= url('hr/attendance') ?>" class="sidebar-link <?= activeLink('hr/attendance') ?>">
                <i class="bi bi-clock"></i><span>Présences</span>
            </a>
            <a href="<?= url('hr/leaves') ?>" class="sidebar-link <?= activeLink('hr/leaves') ?>">
                <i class="bi bi-calendar-heart"></i><span>Congés</span>
            </a>
            <a href="<?= url('hr/payroll') ?>" class="sidebar-link <?= activeLink('hr/payroll') ?>">
                <i class="bi bi-cash-stack"></i><span>Paie</span>
            </a>
            <?php endif; ?>

            <?php if (in_array(Session::userRole(), ['admin', 'accountant', 'manager'])): ?>
            <!-- Comptabilité -->
            <div class="sidebar-section-label"><span>Comptabilité</span></div>
            <a href="<?= url('accounting/invoices') ?>" class="sidebar-link <?= activeLink('accounting/invoices') ?>">
                <i class="bi bi-receipt"></i><span>Factures</span>
            </a>
            <a href="<?= url('accounting/payments') ?>" class="sidebar-link <?= activeLink('accounting/payments') ?>">
                <i class="bi bi-credit-card"></i><span>Paiements</span>
            </a>
            <a href="<?= url('accounting/expenses') ?>" class="sidebar-link <?= activeLink('accounting/expenses') ?>">
                <i class="bi bi-cart"></i><span>Dépenses</span>
            </a>
            <a href="<?= url('accounting/taxes') ?>" class="sidebar-link <?= activeLink('accounting/taxes') ?>">
                <i class="bi bi-percent"></i><span>Taxes</span>
            </a>
            <a href="<?= url('accounting/reports/balance-sheet') ?>" class="sidebar-link <?= activeLink('accounting/reports') ?>">
                <i class="bi bi-file-earmark-bar-graph"></i><span>Bilan</span>
            </a>
            <a href="<?= url('accounting/reports/income') ?>" class="sidebar-link <?= activeLink('accounting/reports') ?>">
                <i class="bi bi-graph-up"></i><span>Résultat</span>
            </a>
            <?php endif; ?>

            <?php if (Session::userRole() === 'admin'): ?>
            <!-- Admin -->
            <div class="sidebar-section-label"><span>Administration</span></div>
            <a href="<?= url('settings') ?>" class="sidebar-link <?= activeLink('settings') ?>">
                <i class="bi bi-gear"></i>
                <span>Paramètres</span>
            </a>
            <a href="<?= url('auth/users') ?>" class="sidebar-link <?= activeLink('auth/users') ?>">
                <i class="bi bi-shield-lock"></i>
                <span>Utilisateurs</span>
            </a>
            <?php endif; ?>
        </nav>

        <!-- Sidebar Footer — User -->
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    <?= strtoupper(substr(Session::user()['prenom'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="sidebar-user-info">
                    <span class="sidebar-user-name"><?= e(Session::user()['prenom'] ?? '') . ' ' . e(Session::user()['nom'] ?? '') ?></span>
                    <span class="sidebar-user-role"><?= e(Session::user()['role'] ?? '') ?></span>
                </div>
                <div class="sidebar-user-menu">
                    <a href="<?= url('auth/profile') ?>" title="Profil"><i class="bi bi-gear"></i></a>
                    <a href="<?= url('auth/logout') ?>" title="Déconnexion"><i class="bi bi-box-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </aside>

    <!-- Sidebar overlay for mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" role="presentation" aria-hidden="true"></div>

    <!-- Main Content Area -->
    <div class="app-main">
        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="topbar-toggler" id="mobileSidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <div class="topbar-breadcrumb">
                    <h5 class="mb-0"><?= e($title ?? 'Dashboard') ?></h5>
                </div>
            </div>
            <div class="topbar-right">
                <span class="topbar-date"><?= date('d F Y') ?></span>
            </div>
        </header>

        <!-- Page Content -->
        <main class="app-content">
            <?php if (Session::hasFlash('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e(Session::getFlash('error')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (Session::hasFlash('success')): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle-fill me-2"></i><?= e(Session::getFlash('success')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="<?= asset('js/app.js') ?>" defer></script>
</body>
</html>
