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
  <title>Administration — Clients</title>
  <script>document.documentElement.dataset.theme=localStorage.getItem('esadiss-theme')||'dark';</script>
  <link rel="stylesheet" href="styles.css?v=20260412-1">
</head>
<body data-admin-page="clients">
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
      <section class="admin-section section-clients">
        <h2 class="admin-section__title">Comptes clients</h2>
        <p class="section-hint">Comptes créés via l’inscription publique. Vous pouvez les promouvoir partenaire.</p>
        <div class="btn-row-admin">
          <button type="button" class="btn btn-secondary" id="btn-add-client">+ Ajouter un client</button>
        </div>
        <div id="clients-wrapper">
          <div class="empty-state">Chargement…</div>
        </div>
      </section>
    </section>
  </main>

  <div class="modal-overlay" id="modal-partenaire">
    <div class="modal">
      <div class="modal-header">
        <h2 id="modal-partenaire-title">Ajouter un client</h2>
        <button type="button" class="modal-close" id="modal-partenaire-close" aria-label="Fermer">&times;</button>
      </div>
      <div class="modal-body">
        <form id="form-partenaire">
          <input type="hidden" id="p-role" name="role" value="client">
          <div class="form-group">
            <label for="p-login">Identifiant de connexion *</label>
            <input type="text" id="p-login" name="login" required autocomplete="off">
          </div>
          <div class="form-group">
            <label for="p-password">Mot de passe *</label>
            <input type="password" id="p-password" name="password" required autocomplete="new-password">
          </div>
          <div class="form-group">
            <label for="p-nom">Nom (optionnel)</label>
            <input type="text" id="p-nom" name="nom" autocomplete="off">
          </div>
          <div class="form-group">
            <label for="p-email">Email (optionnel)</label>
            <input type="email" id="p-email" name="email" autocomplete="off">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="modal-partenaire-cancel">Annuler</button>
        <button type="button" class="btn btn-primary" id="btn-create-partenaire">Créer le compte</button>
      </div>
    </div>
  </div>

  <script src="admin.js?v=13"></script>
  <script src="theme-toggle.js"></script>
</body>
</html>
