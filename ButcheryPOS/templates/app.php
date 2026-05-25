<?php
// ButcheryPOS - App Shell Template (sidebar + topbar + content slot)
use App\Core\Auth;
use App\Core\Permission;

$user = Auth::user();
$expiryAlertCount = $_SESSION['expiry_alert_count'] ?? 0;
$accessibleModules = Permission::getAccessibleModules($user['role_id'], $user['role_name']);
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title><?= e($appSettings['company_name'] ?? 'ButcheryPOS') ?> - <?= e($pageTitle ?? t('dashboard')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset_url('css/app.css') ?>" rel="stylesheet">
    <?php if (($_GET['page'] ?? '') === 'pos'): ?>
    <link href="<?= asset_url('css/pos.css') ?>" rel="stylesheet">
    <?php endif; ?>
</head>
<body>

<!-- Sidebar -->
<nav class="app-sidebar">
    <div class="sidebar-brand">
        <h4><i class="bi bi-shop"></i> <?= e($appSettings['company_name'] ?? 'ButcheryPOS') ?></h4>
        <small><?= e($appSettings['company_tagline'] ?? '') ?></small>
    </div>

    <div class="sidebar-nav">
        <div class="nav-section"><?= t('dashboard') ?></div>

        <?php if (in_array('dashboard', $accessibleModules)): ?>
        <a href="<?= url('?page=dashboard') ?>" class="nav-item <?= is_current_page('dashboard') ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2"></i> <?= t('dashboard') ?>
        </a>
        <?php endif; ?>

        <?php if (in_array('pos', $accessibleModules)): ?>
        <a href="<?= url('?page=pos') ?>" class="nav-item <?= is_current_page('pos') ? 'active' : '' ?>">
            <i class="bi bi-cash-register"></i> <?= t('pos') ?>
        </a>
        <?php endif; ?>

        <div class="nav-section"><?= t('products') ?></div>

        <?php if (in_array('products', $accessibleModules)): ?>
        <a href="<?= url('?page=products') ?>" class="nav-item <?= is_current_page('products') ? 'active' : '' ?>">
            <i class="bi bi-box-seam"></i> <?= t('products') ?>
        </a>
        <?php endif; ?>

        <?php if (in_array('categories', $accessibleModules)): ?>
        <a href="<?= url('?page=categories') ?>" class="nav-item <?= is_current_page('categories') ? 'active' : '' ?>">
            <i class="bi bi-tags"></i> <?= t('categories') ?>
        </a>
        <?php endif; ?>

        <?php if (in_array('stock', $accessibleModules)): ?>
        <a href="<?= url('?page=stock') ?>" class="nav-item <?= is_current_page('stock') ? 'active' : '' ?>">
            <i class="bi bi-archive"></i> <?= t('stock') ?>
        </a>
        <?php endif; ?>

        <?php if (in_array('breakdown', $accessibleModules)): ?>
        <a href="<?= url('?page=breakdown') ?>" class="nav-item <?= is_current_page('breakdown') ? 'active' : '' ?>">
            <i class="bi bi-scissors"></i> <?= t('breakdown') ?>
        </a>
        <?php endif; ?>

        <?php if (in_array('expiry', $accessibleModules)): ?>
        <a href="<?= url('?page=expiry') ?>" class="nav-item <?= is_current_page('expiry') ? 'active' : '' ?>">
            <i class="bi bi-clock-history"></i> <?= t('expiry') ?>
            <?php if ($expiryAlertCount > 0): ?>
            <span class="badge bg-danger"><?= $expiryAlertCount ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <div class="nav-section"><?= t('sales') ?></div>

        <?php if (in_array('sales', $accessibleModules)): ?>
        <a href="<?= url('?page=sales') ?>" class="nav-item <?= is_current_page('sales') ? 'active' : '' ?>">
            <i class="bi bi-receipt"></i> <?= t('sales') ?>
        </a>
        <?php endif; ?>

        <?php if (in_array('customers', $accessibleModules)): ?>
        <a href="<?= url('?page=customers') ?>" class="nav-item <?= is_current_page('customers') ? 'active' : '' ?>">
            <i class="bi bi-people"></i> <?= t('customers') ?>
        </a>
        <?php endif; ?>

        <?php if (in_array('suppliers', $accessibleModules)): ?>
        <a href="<?= url('?page=suppliers') ?>" class="nav-item <?= is_current_page('suppliers') ? 'active' : '' ?>">
            <i class="bi bi-truck"></i> <?= t('suppliers') ?>
        </a>
        <?php endif; ?>

        <?php if (in_array('users', $accessibleModules) || in_array('roles', $accessibleModules) || in_array('settings', $accessibleModules)): ?>
        <div class="nav-section"><?= t('settings') ?></div>

        <?php if (in_array('users', $accessibleModules)): ?>
        <a href="<?= url('?page=users') ?>" class="nav-item <?= is_current_page('users') ? 'active' : '' ?>">
            <i class="bi bi-person-badge"></i> <?= t('users') ?>
        </a>
        <?php endif; ?>

        <?php if (in_array('roles', $accessibleModules)): ?>
        <a href="<?= url('?page=roles') ?>" class="nav-item <?= is_current_page('roles') ? 'active' : '' ?>">
            <i class="bi bi-shield-lock"></i> <?= t('roles') ?>
        </a>
        <?php endif; ?>

        <?php if (in_array('settings', $accessibleModules)): ?>
        <a href="<?= url('?page=settings') ?>" class="nav-item <?= is_current_page('settings') ? 'active' : '' ?>">
            <i class="bi bi-gear"></i> <?= t('settings') ?>
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</nav>

<!-- Main Content -->
<div class="app-main">
    <!-- Top Bar -->
    <header class="app-topbar">
        <div class="topbar-left">
            <button id="sidebar-toggle" class="btn btn-sm btn-outline-secondary d-md-none">
                <i class="bi bi-list"></i>
            </button>
            <h6 class="mb-0"><?= e($pageTitle ?? t('dashboard')) ?></h6>
        </div>
        <div class="topbar-right">
            <!-- Language Switcher -->
            <form method="POST" action="<?= url('?action=set_language') ?>" class="d-flex align-items-center gap-1">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
                <button type="submit" name="lang" value="fr"
                    class="btn btn-sm btn-outline-secondary <?= $lang === 'fr' ? 'active' : '' ?>">FR</button>
                <button type="submit" name="lang" value="en"
                    class="btn btn-sm btn-outline-secondary <?= $lang === 'en' ? 'active' : '' ?>">EN</button>
            </form>

            <!-- User Menu -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle"></i> <?= e($user['full_name'] ?? '') ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small text-muted"><?= e($user['role_display'] ?? '') ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="<?= url('?action=logout') ?>">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <button class="dropdown-item text-danger" type="submit">
                                <i class="bi bi-box-arrow-left"></i> <?= t('logout') ?>
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Flash Messages -->
    <?php foreach (pull_flashes() as $flash): ?>
    <div class="alert-flash alert alert-<?= e($flash['type']) ?> alert-dismissible fade show m-3">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endforeach; ?>

    <!-- Page Content -->
    <main class="app-content">
        <?= $content ?? '' ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('js/app.js') ?>"></script>
<?php if (($_GET['page'] ?? '') === 'pos'): ?>
<script src="<?= asset_url('js/scale.js') ?>"></script>
<script src="<?= asset_url('js/pos.js') ?>"></script>
<?php endif; ?>
<?php if (($_GET['page'] ?? '') === 'breakdown'): ?>
<script src="<?= asset_url('js/breakdown.js') ?>"></script>
<?php endif; ?>
</body>
</html>