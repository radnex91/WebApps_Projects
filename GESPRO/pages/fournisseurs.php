<?php /* pages/fournisseurs.php */
$pageTitle = 'Fournisseurs — ' . APP_TITLE;
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout_top.php';

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        'code' => strtoupper(trim($_POST['code'] ?? '')),
        'nom'  => trim($_POST['nom'] ?? ''),
        'type' => $_POST['type'] ?? 'fournisseur',
    ];
    if ($id) {
        execute("UPDATE fournisseurs SET code=:code, nom=:nom, type=:type WHERE id=:id", [...$data,'id'=>$id]);
        $success = "Fournisseur mis à jour.";
    } else {
        execute("INSERT INTO fournisseurs (code, nom, type) VALUES (:code,:nom,:type)", $data);
        $success = "Fournisseur ajouté.";
    }
}

$fournisseurs = query("SELECT * FROM fournisseurs WHERE actif=1 ORDER BY nom");
?>
<div class="page-header"><h1>Fournisseurs & Affectations</h1><p>Gérer les fournisseurs, chantiers et intervenants</p></div>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="filter-bar mb-16">
  <span class="text-muted"><?= count($fournisseurs) ?> fournisseurs</span>
  <button onclick="App.openModal('modalFourn')" class="btn btn-primary ml-auto">+ Ajouter</button>
</div>

<div class="table-wrap">
  <table>
    <thead><tr><th>Code</th><th>Nom</th><th>Type</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($fournisseurs as $f): ?>
    <tr>
      <td class="mono"><?= htmlspecialchars($f['code']) ?></td>
      <td class="bold"><?= htmlspecialchars($f['nom']) ?></td>
      <td><span class="badge <?= $f['type']==='fournisseur'?'badge-blue':($f['type']==='chantier'?'badge-gold':'badge-gray') ?>"><?= $f['type'] ?></span></td>
      <td><button onclick="editFourn(<?= htmlspecialchars(json_encode($f)) ?>)" class="btn btn-secondary btn-sm">Modifier</button></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal-overlay" id="modalFourn">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="modalFournTitle">Ajouter fournisseur</span>
      <button class="modal-close" onclick="App.closeModal('modalFourn')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="id" id="fournId" value="">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Code *</label><input type="text" name="code" id="fournCode" class="form-control" required placeholder="BAKIDJA, NGB..."></div>
          <div class="form-group"><label class="form-label">Type</label>
            <select name="type" id="fournType" class="form-control">
              <option value="fournisseur">Fournisseur</option>
              <option value="chantier">Chantier</option>
              <option value="interne">Interne</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label">Nom complet *</label><input type="text" name="nom" id="fournNom" class="form-control" required></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="App.closeModal('modalFourn')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<script>
function editFourn(f) {
  document.getElementById('modalFournTitle').textContent = 'Modifier: ' + f.nom;
  document.getElementById('fournId').value   = f.id;
  document.getElementById('fournCode').value = f.code;
  document.getElementById('fournNom').value  = f.nom;
  document.getElementById('fournType').value = f.type;
  App.openModal('modalFourn');
}
</script>
<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
