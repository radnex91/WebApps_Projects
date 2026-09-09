<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('fournisseurs.voir');
$db     = getDB();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['action'] ?? '';

    // Suppression (désactivation) en POST + CSRF — jamais en GET.
    if ($postAction === 'delete' && $id && hasPermission('fournisseurs.supprimer')) {
        try {
            $db->prepare("UPDATE fournisseurs SET actif=0 WHERE id=?")->execute([$id]);
            flash('Fournisseur désactivé.');
        } catch (Throwable $e) {
            flashError($e, 'désactivation fournisseur');
        }
        header('Location: ' . url('fournisseurs')); exit;
    }

    if (hasPermission('fournisseurs.ajouter') || hasPermission('fournisseurs.modifier')) {
        $nom      = trim($_POST['nom'] ?? '');
        $contact  = trim($_POST['contact'] ?? '');
        $tel      = trim($_POST['telephone'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $adresse  = trim($_POST['adresse'] ?? '');
        $ville    = trim($_POST['ville'] ?? '');
        $actif    = (int)($_POST['actif'] ?? 1);

        if ($nom === '') {
            flash('Le nom du fournisseur est requis.', 'error');
            header('Location: ' . ($id ? url('fournisseurs', ['action'=>'edit','id'=>$id]) : url('fournisseurs', ['action'=>'add']))); exit;
        }

        try {
            if ($id) {
                $db->prepare("UPDATE fournisseurs SET nom=?,contact=?,telephone=?,email=?,adresse=?,ville=?,actif=? WHERE id=?")
                   ->execute([$nom,$contact,$tel,$email,$adresse,$ville,$actif,$id]);
                flash('Fournisseur mis à jour.');
            } else {
                $db->prepare("INSERT INTO fournisseurs (nom,contact,telephone,email,adresse,ville,actif) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$nom,$contact,$tel,$email,$adresse,$ville,$actif]);
                flash('Fournisseur ajouté.');
            }
        } catch (Throwable $e) {
            flashError($e, 'enregistrement fournisseur');
        }
        header('Location: ' . url('fournisseurs')); exit;
    }
}

if (in_array($action, ['add', 'edit'])) {
    $f = ['nom'=>'','contact'=>'','telephone'=>'','email'=>'','adresse'=>'','ville'=>'','actif'=>1];
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM fournisseurs WHERE id=?");
        $stmt->execute([$id]); $fetched = $stmt->fetch();
        if ($fetched) $f = $fetched;
    }
    layout_head(($id ? 'Modifier' : 'Ajouter un') . ' fournisseur', 'fournisseurs');
    showFlash();
    ?>
    <div class="card" style="max-width:720px;margin:0 auto;">
      <div class="card-header">
        <div class="card-title"><?= $id ? 'Modifier fournisseur' : 'Nouveau fournisseur' ?></div>
        <a href="<?= url('fournisseurs') ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left',14) ?> Retour</a>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-grid">
          <div class="form-group full">
            <label>Nom de la société *</label>
            <input type="text" name="nom" value="<?= e($f['nom']) ?>" required>
          </div>
          <div class="form-group">
            <label>Contact (responsable)</label>
            <input type="text" name="contact" value="<?= e($f['contact']) ?>">
          </div>
          <div class="form-group">
            <label>Téléphone</label>
            <input type="text" name="telephone" value="<?= e($f['telephone']) ?>">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="<?= e($f['email']) ?>">
          </div>
          <div class="form-group">
            <label>Ville</label>
            <input type="text" name="ville" value="<?= e($f['ville']) ?>">
          </div>
          <div class="form-group">
            <label>Statut</label>
            <select name="actif">
              <option value="1" <?= $f['actif']?'selected':'' ?>>Actif</option>
              <option value="0" <?= !$f['actif']?'selected':'' ?>>Inactif</option>
            </select>
          </div>
          <div class="form-group full">
            <label>Adresse</label>
            <textarea name="adresse"><?= e($f['adresse']) ?></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <a href="<?= url('fournisseurs') ?>" class="btn btn-ghost">Annuler</a>
          <button type="submit" class="btn btn-primary"><?= icon('save',14) ?> Enregistrer</button>
        </div>
      </form>
    </div>
    <?php layout_foot(); exit;
}

// ── Export Excel ─────────────────────────────────────────────
if (($_GET['export'] ?? '') === '1') {
    require_once __DIR__ . '/../includes/export_xlsx.php';
    $rowsX = [];
    foreach ($db->query("
        SELECT f.nom, f.contact, f.telephone, f.email, f.ville, f.adresse, f.actif, f.created_at,
               COUNT(p.id) AS nb_produits
        FROM fournisseurs f
        LEFT JOIN produits p ON p.fournisseur_id = f.id AND p.actif = 1
        GROUP BY f.id ORDER BY f.nom
    ")->fetchAll() as $f) {
        $rowsX[] = [$f['nom'], $f['contact'], $f['telephone'], $f['email'], $f['adresse'], $f['ville'],
                    (int)$f['nb_produits'], (int)$f['actif'] ? 'Actif' : 'Inactif',
                    date('d/m/Y', strtotime($f['created_at']))];
    }
    export_xlsx_send('fournisseurs_' . date('Y-m-d'), 'Fournisseurs',
        ['Nom', 'Contact', 'Téléphone', 'Email', 'Adresse', 'Ville', 'Nb produits', 'Statut', 'Créé le'], $rowsX);
}

// Pagination serveur — section Gestion : 25/page
require_once __DIR__ . '/../includes/pagination.php';
$perPage     = 25;
$page        = max(1, (int)($_GET['page'] ?? 1));
$totalFourn  = (int)$db->query("SELECT COUNT(*) FROM fournisseurs")->fetchColumn();
$offsetFourn = paginateOffset($page, $perPage);

$fournisseurs = $db->prepare("
    SELECT f.*, COUNT(p.id) AS nb_produits
    FROM fournisseurs f
    LEFT JOIN produits p ON p.fournisseur_id = f.id AND p.actif = 1
    GROUP BY f.id ORDER BY f.nom
    LIMIT $perPage OFFSET $offsetFourn
");
$fournisseurs->execute();
$fournisseurs = $fournisseurs->fetchAll();

layout_head('Fournisseurs', 'fournisseurs');
showFlash();
?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Fournisseurs</div>
    <a href="<?= url('fournisseurs', ['export'=>'1']) ?>" class="btn btn-ghost btn-sm" title="Exporter au format Excel (.xlsx)"><?= icon('download',14) ?> Exporter</a>
    <?php if (hasPermission('fournisseurs.ajouter')): ?>
    <a href="<?= url('fournisseurs', ['action'=>'add']) ?>" class="btn btn-primary btn-sm"><?= icon('plus',14) ?> Ajouter</a>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Société</th><th>Contact</th><th>Téléphone</th>
          <th>Email</th><th>Ville</th><th>Produits</th>
          <th>Statut</th><?php if(hasPermission('fournisseurs.modifier') || hasPermission('fournisseurs.supprimer')):?><th>Actions</th><?php endif;?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($fournisseurs as $f): ?>
        <tr>
          <td class="td-name"><?= e($f['nom']) ?></td>
          <td><?= e($f['contact'] ?? '—') ?></td>
          <td class="fw-mono"><?= e($f['telephone'] ?? '—') ?></td>
          <td class="text-sm"><?= e($f['email'] ?? '—') ?></td>
          <td><?= e($f['ville'] ?? '—') ?></td>
          <td><span class="badge badge-blue"><?= $f['nb_produits'] ?></span></td>
          <td><span class="badge <?= $f['actif']?'badge-green':'badge-red' ?>"><?= $f['actif']?'Actif':'Inactif' ?></span></td>
          <?php if (hasPermission('fournisseurs.modifier') || hasPermission('fournisseurs.supprimer')): ?>
          <td>
            <div class="flex gap-8">
              <a href="<?= url('fournisseurs', ['action'=>'edit','id'=>$f['id']], $f['nom'] ?? null) ?>" class="btn btn-ghost btn-xs"><?= icon('edit',13) ?></a>
              <?php if (hasPermission('fournisseurs.supprimer') && $f['nb_produits'] == 0): ?>
              <button class="btn btn-danger btn-xs" onclick="confirmDeletePost('delete', <?= (int)$f['id'] ?>, 'Désactiver ce fournisseur ?')">
                <?= icon('trash',13) ?>
              </button>
              <?php endif; ?>
            </div>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (!$fournisseurs): ?>
        <tr><td colspan="8">
          <div class="empty">
            <div style="color:var(--text3);margin-bottom:8px;"><?= icon('building',36) ?></div>
            <div>Aucun fournisseur</div>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= renderPagination($page, $perPage, $totalFourn, []) ?>
</div>
<?php layout_foot(); ?>
