<?php
requireLogin();
$user = currentUser();
$userRole = $user['role_nom'] ?? '';
$flash = getFlash();

// Detect current page/module for active nav state
$currentScript = $_SERVER['SCRIPT_FILENAME'] ?? '';
$currentModule = '';
if (strpos($currentScript, '/modules/') !== false) {
    preg_match('/\/modules\/([^\/]+)\//', $currentScript, $m);
    $currentModule = $m[1] ?? '';
} elseif (strpos($currentScript, 'dashboard') !== false) {
    $currentModule = 'dashboard';
}

function navItem(string $href, string $icon, string $label, string $module, string $current): string {
    $active = ($module === $current) ? 'active' : '';
    $ariaCurrent = ($module === $current) ? ' aria-current="page"' : '';
    return "<a href=\"$href\" class=\"nav-item $active\"$ariaCurrent><span class=\"nav-icon\"><i class=\"ph-bold $icon\"></i></span><span class=\"nav-label\">$label</span></a>";
}

$initials = strtoupper(substr($user['prenom'] ?? '', 0, 1) . substr($user['nom'] ?? '', 0, 1));

// Notifications for current user
$notifDb = getDB();
$notifUnread = $notifDb->prepare("SELECT COUNT(*) as n FROM notifications WHERE utilisateur_id=? AND lue=0");
$notifUnread->execute([$_SESSION['user_id']]);
$notifCount = (int)$notifUnread->fetchColumn();
$notifList = $notifDb->prepare("SELECT n.*, de.numero as eng_numero FROM notifications n LEFT JOIN demandes_engagement de ON n.engagement_id=de.id WHERE n.utilisateur_id=? ORDER BY n.created_at DESC LIMIT 10");
$notifList->execute([$_SESSION['user_id']]);
$notifications = $notifList->fetchAll();
$notifIcons = ['soumis'=>'<i class="fa-solid fa-clock"></i>','valide'=>'<i class="fa-solid fa-check"></i>','approuve'=>'<i class="fa-solid fa-check-double"></i>','rejete'=>'<i class="fa-solid fa-xmark"></i>','renvoye'=>'<i class="fa-solid fa-arrow-rotate-left"></i>','execute'=>'<i class="fa-solid fa-coins"></i>'];

// Load entreprise settings for theme/logo
$entreprise = getEntreprise();
$entTheme = $entreprise['theme'] ?? 'default';
$entLogo = $entreprise['logo'] ?? '';
$entSigle = $entreprise['sigle'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars($entTheme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="<?=
  match($entTheme) {
    'wallstreet' => '#0a0a0a', 'cyberpunk' => '#0d001a', 'aurora' => '#020024',
    'executive' => '#0a0a0f', 'solar' => '#1a0f00', 'ocean' => '#03045e',
    'royal' => '#1a0033', 'emerald' => '#041e15', default => '#0f3060'
  }
?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title><?= htmlspecialchars($pageTitle ?? 'Tableau de bord') ?> — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/bold/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
<style>
  body { font-family: 'Manrope', system-ui, -apple-system, sans-serif; }
</style>
</head>
<body>

<!-- Skip to content link for keyboard users -->
<a href="#main-content" class="skip-link">Aller au contenu principal</a>

<!-- Overlay mobile -->
<div id="sidebar-overlay" onclick="closeMobileSidebar()" role="presentation"></div>

<!-- ═══ SIDEBAR ═══════════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Navigation principale">
  <div class="sidebar-brand">
    <div class="brand-icon" aria-hidden="true"><i class="ph-bold ph-money"></i></div>
    <div>
      <div class="brand-name"><?= APP_NAME ?></div>
      <div class="brand-sub">Gestion Caisse · Trésorerie · Finance</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section">Principal</div>
    <?= navItem(BASE_URL.'/dashboard.php', 'ph-squares-four', 'Tableau de bord', 'dashboard', $currentModule) ?>

    <?php
    $showOps = canSeeModule('caisse') || canSeeModule('operations_caisse') || canSeeModule('tresorerie') || canSeeModule('engagements') || canSeeModule('ordre_mission') || canSeeModule('decharge');
    ?>
    <?php if ($showOps): ?>
    <div class="nav-section">Opérations</div>
    <?php if ($userRole === 'caissier'): ?>
    <?= navItem(BASE_URL.'/modules/caisse/index.php',              'ph-wallet', 'Caisse',            'caisse',              $currentModule) ?>
    <?php endif; ?>
    <?php if (canSeeModule('operations_caisse')): ?>
    <?= navItem(BASE_URL.'/modules/operations_caisse/index.php',  'ph-arrows-left-right', 'Opérations caisse', 'operations_caisse',   $currentModule) ?>
    <?php endif; ?>
    <?php if (canSeeModule('tresorerie')): ?>
    <?= navItem(BASE_URL.'/modules/tresorerie/index.php',         'ph-bank', 'Trésorerie',        'tresorerie',          $currentModule) ?>
    <?php endif; ?>
    <?php if (canSeeModule('engagements')): ?>
    <?= navItem(BASE_URL.'/modules/engagements/index.php',        'ph-clipboard-text', 'Engagements',       'engagements',         $currentModule) ?>
    <?php endif; ?>
    <?php if (canSeeModule('ordre_mission')): ?>
    <?= navItem(BASE_URL.'/modules/ordre_mission/index.php',      'ph-airplane', 'Ordres de mission', 'ordre_mission',       $currentModule) ?>
    <?php endif; ?>
    <?php if (canSeeModule('decharge')): ?>
    <?= navItem(BASE_URL.'/modules/decharge/index.php',           'ph-receipt', 'Décharges',         'decharge',            $currentModule) ?>
    <?php endif; ?>
    <?php if (canSeeModule('bons_commande')): ?>
    <?= navItem(BASE_URL.'/modules/bons_commande/index.php',    'ph-file-text', 'Bons de commande', 'bons_commande',       $currentModule) ?>
    <?php endif; ?>
    <?php if (canSeeModule('paie')): ?>
    <?= navItem(BASE_URL.'/modules/paie/index.php',              'ph-currency-dollar', 'Paie',     'paie',               $currentModule) ?>
    <?php endif; ?>
    <?php if (canSeeModule('rh')): ?>
    <?= navItem(BASE_URL.'/modules/rh/index.php', 'ph-users-three', 'Ressources Humaines', 'rh', $currentModule) ?>
    <?php endif; ?>
    <?php endif; ?>

    <?php
    $showFinance = canSeeModule('comptabilite') || canSeeModule('budget') || canSeeModule('cloture') || canSeeModule('exercices') || canSeeModule('compta_analytique');
    ?>
    <?php if ($showFinance): ?>
    <div class="nav-section">Finance</div>
    <?php if (canSeeModule('comptabilite')): ?>
    <?= navItem(BASE_URL.'/modules/comptabilite/index.php', 'ph-book-open', 'Comptabilité',  'comptabilite', $currentModule) ?>
    <?php endif; ?>
    <?php if (canSeeModule('budget')): ?>
    <?= navItem(BASE_URL.'/modules/budget/index.php',       'ph-chart-pie-slice', 'Budget',         'budget',       $currentModule) ?>
    <?php endif; ?>
    <?php if (canSeeModule('cloture') || canSeeModule('exercices')): ?>
    <?= navItem(BASE_URL.'/modules/cloture/index.php',      'ph-door', 'Clôture',         'cloture',      $currentModule) ?>
    <?php endif; ?>
    <?php if (canSeeModule('compta_analytique')): ?>
    <?= navItem(BASE_URL.'/modules/compta_analytique/index.php', 'ph-chart-donut', 'Compta analytique', 'compta_analytique', $currentModule) ?>
    <?php endif; ?>
    <?php endif; ?>

    <?php
    $showCtrl = canSeeModule('reporting') || canSeeModule('audit');
    ?>
    <?php if ($showCtrl): ?>
    <div class="nav-section">Contrôle</div>
    <?php if (canSeeModule('reporting')): ?>
    <?= navItem(BASE_URL.'/modules/reporting/index.php',    'ph-chart-bar', 'Reporting',     'reporting',    $currentModule) ?>
    <?php endif; ?>
    <?php if (canSeeModule('audit')): ?>
    <?= navItem(BASE_URL.'/modules/audit/index.php',        'ph-shield-check', 'Audit',          'audit',        $currentModule) ?>
    <?php endif; ?>
    <?php endif; ?>

    <?php
    $showSettings = canSeeModule('admin') || canSeeModule('referentiels');
    ?>
    <?php if ($showSettings): ?>
    <div class="nav-section">Paramètres</div>
    <?php if (canSeeModule('admin')): ?>
    <?= navItem(BASE_URL.'/modules/admin/index.php',        'ph-gear-six', 'Administration', 'admin',        $currentModule) ?>
    <?php endif; ?>
    <?php if (canSeeModule('referentiels')): ?>
    <?= navItem(BASE_URL.'/modules/referentiels/index.php', 'ph-database', 'Référentiels',  'referentiels', $currentModule) ?>
    <?php endif; ?>
    <?php if (hasPermission('cloture','gerer_exercice') || hasPermission('admin','all')): ?>
    <?= navItem(BASE_URL.'/modules/exercices/index.php',    'ph-calendar-check', 'Exercices',     'exercices',    $currentModule) ?>
    <?php endif; ?>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-avatar" aria-hidden="true"><?= $initials ?></div>
    <div class="user-details">
      <div class="user-name"><?= htmlspecialchars(($user['nom'] ?? '') . ' ' . ($user['prenom'] ?? '')) ?></div>
      <div class="user-role"><?= htmlspecialchars($user['role_nom'] ?? '') ?></div>
    </div>
    <a href="<?= BASE_URL ?>/logout.php" class="btn-logout" title="Déconnexion" aria-label="Se déconnecter"><i class="ph-bold ph-sign-out"></i></a>
  </div>
</aside>

<!-- ═══ MAIN ══════════════════════════════════════════════════════ -->
<div class="main-wrap" id="main-wrap">

  <!-- TOPBAR -->
  <header class="topbar" role="banner">
    <button class="sidebar-toggle" id="sidebar-toggle" onclick="toggleSidebar()" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="sidebar">
      <span id="toggle-icon" aria-hidden="true">☰</span>
    </button>
    <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Tableau de bord') ?></div>
    <div class="topbar-right">
      <div class="notif-wrap" id="notif-wrap">
        <button class="notif-bell" onclick="toggleNotifDropdown()" aria-label="Notifications<?= $notifCount > 0 ? " ($notifCount non lues)" : '' ?>" aria-haspopup="true" aria-expanded="false">
          <i class="ph-bold ph-bell"></i>
          <?php if ($notifCount > 0): ?>
          <span class="notif-badge" id="notif-badge" aria-hidden="true"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
          <?php endif; ?>
        </button>
        <div class="notif-dropdown" id="notif-dropdown" role="menu" aria-label="Notifications">
          <div class="notif-header">
            <strong>Notifications</strong>
            <?php if ($notifCount > 0): ?>
            <button class="notif-mark-all" onclick="markAllRead()" role="menuitem">Tout marquer comme lu</button>
            <?php endif; ?>
          </div>
          <div class="notif-list" id="notif-list" role="menu">
            <?php if (empty($notifications)): ?>
            <div class="notif-empty" role="menuitem">Aucune notification</div>
            <?php else: foreach($notifications as $n): ?>
            <div class="notif-item <?= $n['lue'] ? '' : 'unread' ?>" data-id="<?= $n['id'] ?>" role="menuitem" tabindex="-1">
              <span class="notif-icon" aria-hidden="true"><?= $notifIcons[$n['type']] ?? '<i class="fa-solid fa-circle"></i>' ?></span>
              <div class="notif-content">
                <div class="notif-title"><?= sanitize($n['titre']) ?></div>
                <div class="notif-msg"><?= sanitize($n['message']) ?></div>
                <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
              </div>
              <?php if ($n['eng_numero']): ?>
              <a href="<?= BASE_URL ?>/modules/engagements/index.php" class="notif-link" title="Voir l'engagement" aria-label="Voir l'engagement <?= htmlspecialchars($n['eng_numero']) ?>">↗</a>
              <?php endif; ?>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>
      <span class="topbar-date" id="live-clock" aria-live="polite" aria-atomic="true"></span>
    </div>
  </header>

  <!-- FLASH TOAST CONTAINER -->
  <div id="toast-container" role="status" aria-live="polite" aria-atomic="true">
    <?php if ($flash):
      $toastIcons = ['success' => '&#10003;', 'danger' => '&#10005;', 'warning' => '!', 'info' => 'i'];
      $toastIcon = $toastIcons[$flash['type']] ?? 'i';
    ?>
    <div class="toast toast-<?= htmlspecialchars($flash['type']) ?>" role="alert">
      <span class="toast-icon"><?= $toastIcon ?></span>
      <span class="toast-msg"><?= htmlspecialchars($flash['message']) ?></span>
      <button class="toast-close" onclick="dismissToast(this.parentElement)" aria-label="Fermer la notification">&times;</button>
      <div class="toast-progress"></div>
    </div>
    <?php endif; ?>
  </div>

  <main class="content" id="main-content">