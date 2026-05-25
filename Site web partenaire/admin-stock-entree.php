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
  <title>Administration — Entrée en stock</title>
  <script>document.documentElement.dataset.theme=localStorage.getItem('esadiss-theme')||'dark';</script>
  <link rel="stylesheet" href="styles.css?v=20260412-1">
</head>
<body data-admin-page="stock-entree">
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
        <p class="section-hint">Enregistrez une <strong>réception</strong> (entrée). Les articles à stock zéro ne sont plus proposés aux partenaires mais restent visibles sur le catalogue public.</p>
        <?php esadiss_stock_subnav($self); ?>
        <form id="form-stock" class="form-stock card-admin">
          <input type="hidden" id="stock-type" name="type" value="entree">
          <div class="form-row form-row-stock">
            <div class="form-group">
              <label for="stock-produit">Produit</label>
              <select id="stock-produit" required></select>
            </div>
            <div class="form-group">
              <label for="stock-rayon">Emplacement (dépôt — rayon)</label>
              <select id="stock-rayon" required></select>
            </div>
          </div>
          <div class="form-row form-row-stock">
            <div class="form-group">
              <label for="stock-qty">Quantité</label>
              <input type="number" id="stock-qty" min="1" step="1" value="1" required>
            </div>
            <div class="form-group">
              <label for="stock-comment">Commentaire (optionnel)</label>
              <input type="text" id="stock-comment" maxlength="500" placeholder="N° bon de livraison, fournisseur…">
            </div>
          </div>
          <button type="submit" class="btn btn-primary" id="stock-submit">Enregistrer l’entrée</button>
        </form>
        <h3 class="stock-subtitle">Dernières entrées</h3>
        <div id="stock-history-wrapper">
          <div class="empty-state">Chargement…</div>
        </div>
      </section>
    </section>
  </main>
  <script src="admin.js?v=13"></script>
  <script src="theme-toggle.js"></script>
</body>
</html>
