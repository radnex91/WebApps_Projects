<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title ?? APP_NAME) ?> — <?= APP_NAME ?></title>
  <!-- Bootstrap 5 (local-first, CDN fallback) -->
  <link rel="stylesheet" href="<?= APP_URL ?>/public/css/bootstrap.min.css">
  <!-- Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- App styles -->
  <link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css">
</head>
<body>

<!-- ── Sidebar ─────────────────────────────────────────────── -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="brand">
      <div class="brand-icon">H</div>
      <div>
        <div class="brand-name"><?= APP_NAME ?></div>
        <div class="brand-sub"><?= e(HOTEL_NOM) ?></div>
      </div>
    </div>
  </div>

  <div class="sidebar-body">
    <div class="nav-label">Principal</div>
    <a href="<?= APP_URL ?>/index.php?page=dashboard"    class="nav-link-item <?= ($page ?? '') === 'dashboard'    ? 'active' : '' ?>">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a href="<?= APP_URL ?>/index.php?page=reservations" class="nav-link-item <?= ($page ?? '') === 'reservations' ? 'active' : '' ?>">
      <i class="bi bi-calendar-check"></i> Réservations
    </a>
    <a href="<?= APP_URL ?>/index.php?page=chambres"     class="nav-link-item <?= ($page ?? '') === 'chambres'     ? 'active' : '' ?>">
      <i class="bi bi-door-open"></i> Chambres
    </a>
    <a href="<?= APP_URL ?>/index.php?page=clients"      class="nav-link-item <?= ($page ?? '') === 'clients'      ? 'active' : '' ?>">
      <i class="bi bi-people"></i> Clients
    </a>

    <div class="nav-label">Finance</div>
    <a href="<?= APP_URL ?>/index.php?page=facturation"  class="nav-link-item <?= ($page ?? '') === 'facturation'  ? 'active' : '' ?>">
      <i class="bi bi-receipt"></i> Facturation
    </a>
    <a href="<?= APP_URL ?>/index.php?page=paiements"    class="nav-link-item <?= ($page ?? '') === 'paiements'    ? 'active' : '' ?>">
      <i class="bi bi-credit-card"></i> Paiements
    </a>

    <div class="nav-label">Administration</div>
    <?php if (has_permission('all') || has_permission('personnel_view')): ?>
    <a href="<?= APP_URL ?>/index.php?page=personnel"    class="nav-link-item <?= ($page ?? '') === 'personnel'    ? 'active' : '' ?>">
      <i class="bi bi-person-badge"></i> Personnel
    </a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/index.php?page=rapports"     class="nav-link-item <?= ($page ?? '') === 'rapports'     ? 'active' : '' ?>">
      <i class="bi bi-bar-chart-line"></i> Rapports
    </a>
  </div>

  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_nom'] ?? 'U', 0, 2)) ?></div>
      <div>
        <div class="user-name"><?= e($_SESSION['user_nom'] ?? '') ?></div>
        <div class="user-role"><?= e($_SESSION['user_role_nom'] ?? '') ?></div>
      </div>
    </div>
    <a href="<?= APP_URL ?>/index.php?page=logout" class="btn-logout" title="Déconnexion">
      <i class="bi bi-box-arrow-right"></i>
    </a>
  </div>
</nav>

<!-- ── Main wrapper ────────────────────────────────────────── -->
<div class="main-wrapper" id="mainWrapper">

  <!-- Top bar -->
  <header class="topbar">
    <button class="btn-menu-toggle" onclick="toggleSidebar()" title="Menu">
      <i class="bi bi-list"></i>
    </button>
    <h1 class="page-title"><?= e($page_title ?? 'Dashboard') ?></h1>
    <div class="topbar-right">
      <span class="text-muted small"><?= date('l d F Y') ?></span>
    </div>
  </header>

  <!-- Flash messages -->
  <div class="flash-zone">
    <?php foreach (get_flash() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
      <i class="bi bi-<?= $f['type'] === 'success' ? 'check-circle' : ($f['type'] === 'danger' ? 'x-circle' : 'exclamation-circle') ?>"></i>
      <?= $f['text'] /* HTML autorisé dans les flash */ ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Page content -->
  <main class="page-content">
