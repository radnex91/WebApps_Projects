<?php
require_once __DIR__ . '/auth.php';
requireLogin('admin');
$user = getCurrentUser();
require_once __DIR__ . '/admin-nav.inc.php';
require_once __DIR__ . '/admin-facturation-subnav.inc.php';
$self = basename(__FILE__);
$factureId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$factureId) { header('Location: admin-facturation.php'); exit; }
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administration — Facture</title>
  <script>document.documentElement.dataset.theme=localStorage.getItem('esadiss-theme')||'dark';</script>
  <link rel="stylesheet" href="styles.css?v=20260412-1">
</head>
<body data-admin-page="facture-view">
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
      <section class="admin-section">
        <?php esadiss_facturation_subnav($self); ?>
        <div id="facture-detail-wrapper">
          <div class="empty-state">Chargement…</div>
        </div>
      </section>
    </section>
  </main>
  <script src="admin.js?v=13"></script>
  <script src="theme-toggle.js"></script>
</body>
</html>