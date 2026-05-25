<?php
require_once __DIR__ . '/auth.php';
requireLogin('admin');
$user = getCurrentUser();
require_once __DIR__ . '/admin-nav.inc.php';
require_once __DIR__ . '/admin-facturation-subnav.inc.php';
$self = basename(__FILE__);
$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administration — <?php echo $editId ? 'Modifier la facture' : 'Nouvelle facture'; ?></title>
  <script>document.documentElement.dataset.theme=localStorage.getItem('esadiss-theme')||'dark';</script>
  <link rel="stylesheet" href="styles.css?v=20260412-1">
</head>
<body data-admin-page="facture-edit">
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
        <h2 class="admin-section__title"><?php echo $editId ? 'Modifier la facture' : 'Nouvelle facture'; ?></h2>
        <?php esadiss_facturation_subnav($self); ?>
        <form id="form-facture" class="form-stock card-admin">
          <input type="hidden" id="facture-id" value="<?php echo $editId; ?>">
          <div class="form-row form-row-stock">
            <div class="form-group">
              <label for="facture-client">Client / Partenaire</label>
              <select id="facture-client" required></select>
            </div>
            <div class="form-group">
              <label for="facture-numero">Numéro</label>
              <input type="text" id="facture-numero" readonly>
            </div>
          </div>
          <div class="form-row form-row-stock">
            <div class="form-group">
              <label for="facture-date">Date</label>
              <input type="date" id="facture-date" required>
            </div>
            <div class="form-group">
              <label for="facture-echeance">Échéance</label>
              <input type="date" id="facture-echeance">
            </div>
            <div class="form-group">
              <label for="facture-statut">Statut</label>
              <select id="facture-statut">
                <option value="brouillon">Brouillon</option>
                <option value="envoyee">Envoyée</option>
                <option value="payee">Payée</option>
                <option value="annulee">Annulée</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label>Lignes de facture</label>
            <div id="facture-lignes-wrapper"></div>
            <button type="button" class="btn btn-secondary" id="btn-add-ligne">+ Ajouter une ligne</button>
          </div>
          <div class="facture-totals">
            <div class="facture-total-row"><span>Total HT</span><span id="facture-total-ht">0</span></div>
            <div class="facture-total-row"><span>TVA (<span id="facture-tva-pct">0</span>%)</span><span id="facture-total-tva">0</span></div>
            <div class="facture-total-row facture-total-ttc"><span>Total TTC</span><span id="facture-total-ttc">0</span></div>
          </div>
          <div class="form-row form-row-stock">
            <div class="form-group">
              <label for="facture-taux-tva">Taux TVA (%)</label>
              <input type="number" id="facture-taux-tva" min="0" max="100" step="0.1" value="0">
            </div>
            <div class="form-group">
              <label for="facture-montant-paye">Montant déjà perçu (avance)</label>
              <input type="number" id="facture-montant-paye" min="0" step="0.01" value="0">
            </div>
          </div>
          <div class="form-group">
            <label for="facture-notes">Notes (optionnel)</label>
            <textarea id="facture-notes" rows="3" placeholder="Notes internes…"></textarea>
          </div>
          <button type="submit" class="btn btn-primary" id="facture-submit">Enregistrer la facture</button>
        </form>
      </section>
    </section>
  </main>
  <script src="admin.js?v=13"></script>
  <script src="theme-toggle.js"></script>
</body>
</html>