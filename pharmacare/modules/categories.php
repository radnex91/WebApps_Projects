<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('categories.gerer');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    // ── Suppression (POST + CSRF) ──
    if (($_POST['action'] ?? '') === 'delete') {
        $did = (int)($_POST['id'] ?? 0);
        $nb  = $db->prepare("SELECT COUNT(*) FROM produits WHERE categorie_id=? AND actif=1");
        $nb->execute([$did]); $nb = $nb->fetchColumn();
        if ($nb > 0) {
            flash("Impossible : $nb produit(s) utilisent cette catégorie.", 'error');
        } else {
            $db->prepare("DELETE FROM categories WHERE id=?")->execute([$did]);
            flash('Catégorie supprimée.');
        }
        header('Location: ' . url('categories')); exit;
    }
    $nom     = trim($_POST['nom'] ?? '');
    $couleur = preg_match('/^#[0-9a-f]{6}$/i', $_POST['couleur']??'') ? $_POST['couleur'] : '#00c9a7';
    $cid     = (int)($_POST['id'] ?? 0);
    if ($nom === '') { flash('Le nom est requis.','error'); header('Location: ' . url('categories')); exit; }
    if ($cid) {
        $db->prepare("UPDATE categories SET nom=?,couleur=? WHERE id=?")->execute([$nom,$couleur,$cid]);
        flash('Catégorie mise à jour.');
    } else {
        $db->prepare("INSERT INTO categories (nom,couleur) VALUES (?,?)")->execute([$nom,$couleur]);
        flash('Catégorie ajoutée.');
    }
    header('Location: ' . url('categories')); exit;
}

// ── Export Excel ─────────────────────────────────────────────
if (($_GET['export'] ?? '') === '1') {
    require_once __DIR__ . '/../includes/export_xlsx.php';
    $rowsX = [];
    foreach ($db->query("
        SELECT c.nom, c.couleur, COUNT(p.id) AS nb
        FROM categories c
        LEFT JOIN produits p ON p.categorie_id=c.id AND p.actif=1
        GROUP BY c.id ORDER BY c.nom
    ")->fetchAll() as $c) {
        $rowsX[] = [$c['nom'], $c['couleur'], (int)$c['nb']];
    }
    export_xlsx_send('categories_' . date('Y-m-d'), 'Catégories',
        ['Nom', 'Couleur', 'Nb produits actifs'], $rowsX);
}

$categories = $db->query("
    SELECT c.*, COUNT(p.id) AS nb
    FROM categories c
    LEFT JOIN produits p ON p.categorie_id=c.id AND p.actif=1
    GROUP BY c.id ORDER BY c.nom
")->fetchAll();

layout_head('Catégories', 'categories');
showFlash();
?>
<div class="grid-2">
  <!-- Liste -->
  <div class="card">
    <div class="card-header"><div class="card-title">Catégories existantes</div>
    <a href="<?= url('categories', ['export'=>'1']) ?>" class="btn btn-ghost btn-sm" title="Exporter au format Excel (.xlsx)"><?= icon('download',14) ?> Exporter</a></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Couleur</th><th>Nom</th><th>Produits</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($categories as $c): ?>
          <tr>
            <td><div style="width:26px;height:26px;border-radius:6px;background:<?= e($c['couleur']) ?>;"></div></td>
            <td class="td-name"><?= e($c['nom']) ?></td>
            <td><span class="badge badge-blue"><?= $c['nb'] ?></span></td>
            <td>
              <div class="flex gap-8">
                <button class="btn btn-ghost btn-xs"
                  onclick="editCat(<?= $c['id'] ?>,<?= json_encode($c['nom']) ?>,<?= json_encode($c['couleur']) ?>)">
                  <?= icon('edit',13) ?>
                </button>
                <?php if ($c['nb'] == 0): ?>
                <button class="btn btn-danger btn-xs"
                  onclick="confirmDeletePost('delete','<?= (int)$c['id'] ?>','Supprimer cette catégorie ?')">
                  <?= icon('trash',13) ?>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$categories): ?>
          <tr><td colspan="4">
            <div class="empty">
              <div style="color:var(--text3);margin-bottom:8px;"><?= icon('tag',28) ?></div>
              <div>Aucune catégorie</div>
            </div>
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Formulaire -->
  <div class="card">
    <div class="card-header"><div class="card-title" id="form-title">Nouvelle catégorie</div></div>
    <form method="POST" id="cat-form">
      <div class="form-grid cols-1" style="padding:20px;gap:14px;">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="id" id="cat-id" value="0">
        <div class="form-group">
          <label>Nom de la catégorie *</label>
          <input type="text" name="nom" id="cat-nom" required placeholder="ex: Antibiotiques">
        </div>
        <div class="form-group">
          <label>Couleur d'identification</label>
          <input type="color" name="couleur" id="cat-couleur" value="#00c9a7"
                 style="padding:4px;height:42px;cursor:pointer;">
        </div>
        <div class="flex gap-8">
          <button type="submit" class="btn btn-primary"><?= icon('save',14) ?> Enregistrer</button>
          <button type="button" class="btn btn-ghost" onclick="resetForm()">Annuler</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function editCat(id, nom, couleur) {
  document.getElementById('cat-id').value = id;
  document.getElementById('cat-nom').value = nom;
  document.getElementById('cat-couleur').value = couleur;
  document.getElementById('form-title').textContent = 'Modifier catégorie';
  document.getElementById('cat-nom').focus();
}
function resetForm() {
  document.getElementById('cat-id').value = 0;
  document.getElementById('cat-nom').value = '';
  document.getElementById('cat-couleur').value = '#00c9a7';
  document.getElementById('form-title').textContent = 'Nouvelle catégorie';
}
</script>
<?php layout_foot(); ?>
