<?php
require_once __DIR__ . '/auth.php';
requireLogin('admin');
$user = getCurrentUser();
require_once __DIR__ . '/admin-nav.inc.php';
require_once __DIR__ . '/admin-stock-subnav.inc.php';
$self = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administration — Dépôts & rayons</title>
  <script>document.documentElement.dataset.theme=localStorage.getItem('esadiss-theme')||'dark';</script>
  <link rel="stylesheet" href="styles.css?v=20260412-1">
</head>
<body data-admin-page="stock-lieux">
  <header>
    <h1>Administration commerciale</h1>
    <div class="header-right">
      <a href="index.php">Catalogue public</a>
      <a href="partenaire.php">Espace partenaire</a>
      <span class="user-name"><?php echo htmlspecialchars($user['login']); ?></span>
      <a href="logout.php">Déconnexion</a>
    </div>
  </header>
  <main class="admin-dashboard">
    <aside class="admin-sidebar">
      <?php esadiss_admin_sidebar($self); ?>
    </aside>
    <section class="admin-main">
      <section class="admin-section section-stock">
        <h2 class="admin-section__title">Gestion des stocks</h2>
        <p class="section-hint">Créez, modifiez ou supprimez les <strong>dépôts</strong> et <strong>rayons</strong> utilisés pour le suivi des emplacements.</p>
        <?php esadiss_stock_subnav($self); ?>
        <div id="lieux-wrapper">
          <div class="empty-state">Chargement…</div>
        </div>
      </section>
    </section>
  </main>
  <script src="admin.js?v=13"></script>
  <script src="theme-toggle.js"></script>
</body>
</html>