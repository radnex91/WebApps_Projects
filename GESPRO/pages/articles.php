<?php
$pageTitle = 'Articles — ' . APP_TITLE;
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout_top.php';

// Handle POST (create/edit)
$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'code'          => strtoupper(trim($_POST['code'] ?? '')),
            'designation'   => trim($_POST['designation'] ?? ''),
            'categorie'     => $_POST['categorie'] ?? 'autre',
            'unite'         => trim($_POST['unite'] ?? 'U'),
            'prix_unitaire' => (float)str_replace(',', '.', $_POST['prix_unitaire'] ?? 0),
            'stock_alerte'  => (float)str_replace(',', '.', $_POST['stock_alerte'] ?? 0),
        ];
        if ($id) {
            execute("UPDATE articles SET code=:code, designation=:designation, categorie=:categorie, unite=:unite, prix_unitaire=:prix_unitaire, stock_alerte=:stock_alerte WHERE id=:id", [...$data, 'id'=>$id]);
            $success = "Article mis à jour.";
        } else {
            execute("INSERT INTO articles (code, designation, categorie, unite, prix_unitaire, stock_alerte) VALUES (:code,:designation,:categorie,:unite,:prix_unitaire,:stock_alerte)", $data);
            $success = "Article créé avec succès.";
        }
    }
}

$articles = query("SELECT * FROM articles WHERE actif=1 ORDER BY designation");
?>

<div class="page-header">
  <h1>Articles / Matériaux</h1>
  <p>Catalogue des matériaux en gestion de stock</p>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="filter-bar mb-16">
  <div class="search-box">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un article...">
  </div>
  <button onclick="App.openModal('modalArticle')" class="btn btn-primary ml-auto">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Nouvel article
  </button>
</div>

<div class="table-wrap">
  <table id="articlesTable">
    <thead>
      <tr>
        <th class="sortable">Code</th>
        <th class="sortable">Désignation</th>
        <th>Catégorie</th>
        <th>Unité</th>
        <th class="text-right sortable">Prix unitaire</th>
        <th class="text-right sortable">Stock actuel</th>
        <th class="text-right">Valeur stock</th>
        <th class="text-right">CMUPACE</th>
        <th>Alerte</th>
        <th style="text-align:center;">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($articles as $a): ?>
    <tr>
      <td class="mono"><?= htmlspecialchars($a['code']) ?></td>
      <td class="bold"><?= htmlspecialchars($a['designation']) ?></td>
      <td><span class="badge badge-gray"><?= htmlspecialchars($a['categorie']) ?></span></td>
      <td class="text-muted"><?= htmlspecialchars($a['unite']) ?></td>
      <td class="amount"><?= number_format($a['prix_unitaire'], 0, ',', ' ') ?></td>
      <td class="mono text-right <?= $a['stock_actuel'] <= $a['stock_alerte'] && $a['stock_alerte'] > 0 ? 'text-red' : 'text-green' ?>">
        <?= number_format($a['stock_actuel'], 0, ',', ' ') ?>
      </td>
      <td class="amount"><?= number_format($a['valeur_stock'], 0, ',', ' ') ?></td>
      <td class="mono text-right text-accent"><?= number_format($a['cmupace'], 0, ',', ' ') ?></td>
      <td class="mono text-muted"><?= $a['stock_alerte'] > 0 ? number_format($a['stock_alerte'], 0, ',', ' ') : '—' ?></td>
      <td>
        <div class="flex gap-8" style="justify-content:center;">
          <a href="/pages/fiche_mensuelle.php?article=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" title="Fiche">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
          </a>
          <button onclick="editArticle(<?= htmlspecialchars(json_encode($a)) ?>)" class="btn btn-secondary btn-sm" title="Modifier">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
          </button>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Modal create/edit article -->
<div class="modal-overlay" id="modalArticle">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="modalArticleTitle">Nouvel Article</span>
      <button class="modal-close" onclick="App.closeModal('modalArticle')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="editId" value="">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Code <span class="required">*</span></label>
            <input type="text" name="code" id="editCode" class="form-control" required placeholder="Ex: FER_10, CIMENT">
          </div>
          <div class="form-group">
            <label class="form-label">Catégorie</label>
            <select name="categorie" id="editCat" class="form-control">
              <option value="ferraillage">Ferraillage</option>
              <option value="boiserie">Boiserie</option>
              <option value="quincaillerie">Quincaillerie</option>
              <option value="carburant">Carburant</option>
              <option value="ciment">Ciment</option>
              <option value="autre">Autre</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Désignation <span class="required">*</span></label>
          <input type="text" name="designation" id="editDesig" class="form-control" required placeholder="Nom complet du matériau">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Unité</label>
            <input type="text" name="unite" id="editUnite" class="form-control" placeholder="U, Kg, L, Sac, Barre...">
          </div>
          <div class="form-group">
            <label class="form-label">Prix unitaire (FCFA)</label>
            <input type="number" name="prix_unitaire" id="editPU" class="form-control" step="1" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Seuil d'alerte</label>
            <input type="number" name="stock_alerte" id="editAlerte" class="form-control" step="1" min="0" value="0">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="App.closeModal('modalArticle')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
tableSearch('searchInput', 'articlesTable');

function editArticle(a) {
  document.getElementById('modalArticleTitle').textContent = 'Modifier: ' + a.designation;
  document.getElementById('editId').value     = a.id;
  document.getElementById('editCode').value   = a.code;
  document.getElementById('editDesig').value  = a.designation;
  document.getElementById('editCat').value    = a.categorie;
  document.getElementById('editUnite').value  = a.unite;
  document.getElementById('editPU').value     = a.prix_unitaire;
  document.getElementById('editAlerte').value = a.stock_alerte;
  App.openModal('modalArticle');
}
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
