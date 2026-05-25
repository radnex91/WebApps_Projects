<?php
require_once __DIR__ . '/auth.php';
requireLogin('admin');
$user = getCurrentUser();
require_once __DIR__ . '/admin-nav.inc.php';
$self = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administration — Produits</title>
  <script>document.documentElement.dataset.theme=localStorage.getItem('esadiss-theme')||'dark';</script>
  <link rel="stylesheet" href="styles.css?v=20260412-1">
</head>
<body data-admin-page="produits">
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
        <div class="admin-actions">
          <h2 class="admin-section__title">Produits et prix</h2>
          <button type="button" class="btn btn-primary" id="btn-add">+ Ajouter un produit</button>
        </div>
        <div id="table-wrapper">
          <div class="empty-state">Chargement…</div>
        </div>
      </section>
    </section>
  </main>

  <div class="modal-overlay" id="modal">
    <div class="modal">
      <div class="modal-header">
        <h2 id="modal-title">Ajouter un produit</h2>
        <button type="button" class="modal-close" id="modal-close" aria-label="Fermer">&times;</button>
      </div>
      <div class="modal-body">
        <form id="form-produit">
          <input type="hidden" id="produit-id" name="id">
          <div class="form-group">
            <label for="nom">Nom du produit *</label>
            <input type="text" id="nom" name="nom" required>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="prix_partenaire">Prix partenaire *</label>
              <input type="number" id="prix_partenaire" name="prix_partenaire" step="0.01" min="0" required>
            </div>
            <div class="form-group">
              <label for="prix_client">Prix client *</label>
              <input type="number" id="prix_client" name="prix_client" step="0.01" min="0" required>
            </div>
          </div>
          <div class="form-group">
            <label for="unite">Unité (€, $, etc.)</label>
            <input type="text" id="unite" name="unite" value="€" maxlength="5">
          </div>
          <div class="form-group is-hidden" id="group-actif">
            <label>
              <input type="checkbox" id="actif" name="actif" checked>
              Produit visible pour les partenaires
            </label>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="modal-cancel">Annuler</button>
        <button type="submit" form="form-produit" class="btn btn-primary" id="modal-save">Enregistrer</button>
      </div>
    </div>
  </div>

  <script src="admin.js?v=13"></script>
  <script src="theme-toggle.js"></script>
</body>
</html>
