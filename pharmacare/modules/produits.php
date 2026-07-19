<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('produits.voir');
$db     = getDB();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ── Suppression (POST + CSRF) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete' && hasPermission('produits.archiver')) {
    verifyCsrf();
    $delId = (int)($_POST['id'] ?? 0);
    if ($delId) {
        $db->prepare("UPDATE produits SET actif=0 WHERE id=?")->execute([$delId]);
        flash('Médicament archivé.');
    }
    header('Location: ' . url('produits')); exit;
}

// ── Sauvegarde (ajout / modification) ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $pid        = (int)($_POST['id'] ?? 0);
    if (!hasPermission($pid ? 'produits.modifier' : 'produits.ajouter')) {
        flash('Accès refusé.', 'error');
        header('Location: ' . url('produits')); exit;
    }
    $nom        = trim($_POST['nom'] ?? '');
    $reference  = trim($_POST['reference'] ?? '');
    $cat_id     = (int)($_POST['categorie_id'] ?? 0);
    $fourn_id   = (int)($_POST['fournisseur_id'] ?? 0);
    $description= trim($_POST['description'] ?? '');
    $stock      = (int)($_POST['stock'] ?? 0);
    $seuil      = (int)($_POST['seuil_alerte'] ?? 10);
    $prix_achat = (float)str_replace(',', '.', $_POST['prix_achat'] ?? 0);
    $prix_vente = (float)str_replace(',', '.', $_POST['prix_vente'] ?? 0);
    $expiry     = $_POST['date_expiration'] ?: null;

    if ($nom === '') {
        flash('Le nom du médicament est requis.', 'error');
        header('Location: ' . ($pid ? url('produits', ['action'=>'edit','id'=>$pid]) : url('produits', ['action'=>'add']))); exit;
    }

    if ($pid) {
        $db->prepare("UPDATE produits SET nom=?,reference=?,categorie_id=?,fournisseur_id=?,description=?,stock=?,seuil_alerte=?,prix_achat=?,prix_vente=?,date_expiration=? WHERE id=?")
           ->execute([$nom,$reference,$cat_id,$fourn_id,$description,$stock,$seuil,$prix_achat,$prix_vente,$expiry,$pid]);
        flash('Médicament mis à jour.');
    } else {
        $db->prepare("INSERT INTO produits (nom,reference,categorie_id,fournisseur_id,description,stock,seuil_alerte,prix_achat,prix_vente,date_expiration) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([$nom,$reference,$cat_id,$fourn_id,$description,$stock,$seuil,$prix_achat,$prix_vente,$expiry]);
        flash('Médicament ajouté avec succès.');
    }
    header('Location: ' . url('produits')); exit;
}

$categories   = $db->query("SELECT * FROM categories ORDER BY nom")->fetchAll();
$fournisseurs = $db->query("SELECT * FROM fournisseurs WHERE actif=1 ORDER BY nom")->fetchAll();

// ── Vue formulaire ───────────────────────────────────────────
if (in_array($action, ['add','edit'])) {
    $p = ['nom'=>'','reference'=>'','categorie_id'=>'','fournisseur_id'=>'','description'=>'',
          'stock'=>0,'seuil_alerte'=>10,'prix_achat'=>0,'prix_vente'=>0,'date_expiration'=>''];
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM produits WHERE id=?");
        $stmt->execute([$id]);
        $fetched = $stmt->fetch();
        if ($fetched) $p = $fetched;
    }
    layout_head(($id ? 'Modifier' : 'Ajouter') . ' un médicament', 'produits');
    showFlash();
    ?>
    <div class="card" style="max-width:820px;margin:0 auto;">
      <div class="card-header">
        <div class="card-title"><?= $id ? 'Modifier médicament' : 'Nouveau médicament' ?></div>
        <a href="<?= url('produits') ?>" class="btn btn-ghost btn-sm">
          <?= icon('chevron-left',14) ?> Retour
        </a>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-grid">
          <div class="form-group full">
            <label>Nom du médicament *</label>
            <input type="text" name="nom" value="<?= e($p['nom']) ?>" required placeholder="ex: Paracétamol 500mg">
          </div>
          <div class="form-group">
            <label>Référence / Code-barres</label>
            <input type="text" name="reference" value="<?= e($p['reference']) ?>" placeholder="Ex: MED-001 ou 3760000000000" style="font-family:'DM Mono',monospace;">
          </div>
          <div class="form-group">
            <label>Catégorie</label>
            <select name="categorie_id">
              <option value="">— Sélectionner —</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $p['categorie_id']==$c['id']?'selected':'' ?>><?= e($c['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Fournisseur</label>
            <select name="fournisseur_id">
              <option value="">— Sélectionner —</option>
              <?php foreach ($fournisseurs as $f): ?>
              <option value="<?= $f['id'] ?>" <?= $p['fournisseur_id']==$f['id']?'selected':'' ?>><?= e($f['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Stock actuel</label>
            <input type="number" name="stock" value="<?= (int)$p['stock'] ?>" min="0" required>
          </div>
          <div class="form-group">
            <label>Seuil d'alerte</label>
            <input type="number" name="seuil_alerte" value="<?= (int)$p['seuil_alerte'] ?>" min="1" required>
          </div>
          <div class="form-group">
            <label>Prix d'achat</label>
            <input type="number" name="prix_achat" value="<?= (float)$p['prix_achat'] ?>" step="1" min="0" required>
          </div>
          <div class="form-group">
            <label>Prix de vente</label>
            <input type="number" name="prix_vente" value="<?= (float)$p['prix_vente'] ?>" step="1" min="0" required>
          </div>
          <div class="form-group">
            <label>Date d'expiration</label>
            <input type="date" name="date_expiration" value="<?= e($p['date_expiration'] ?? '') ?>">
          </div>
          <div class="form-group full">
            <label>Description / Notes</label>
            <textarea name="description"><?= e($p['description']) ?></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <a href="<?= url('produits') ?>" class="btn btn-ghost">Annuler</a>
          <button type="submit" class="btn btn-primary">
            <?= icon('save',14) ?> Enregistrer
          </button>
        </div>
      </form>
    </div>
    <?php layout_foot(); exit;
}

// ── Vue liste ────────────────────────────────────────────────
require_once __DIR__ . '/../includes/pagination.php';
$q     = trim($_GET['q'] ?? '');
$where = "p.actif=1";
$params = [];
if ($q !== '') {
    $qEsc = str_replace(['\\','%','_'], ['\\\\','\%','\_'], $q);
    $where .= " AND (p.nom LIKE ? ESCAPE '\\\\' OR p.reference LIKE ? ESCAPE '\\\\')";
    $params[] = "%$qEsc%";
    $params[] = "%$qEsc%";
}

$perPage = 25;
$page    = max(1, (int)($_GET['page'] ?? 1));
$cntStmt = $db->prepare("SELECT COUNT(*) FROM produits p WHERE $where");
$cntStmt->execute($params);
$totalProduits = (int)$cntStmt->fetchColumn();
$offset  = paginateOffset($page, $perPage);

$query = "SELECT p.*, c.nom AS cat, f.nom AS fourn
          FROM produits p
          LEFT JOIN categories c ON p.categorie_id=c.id
          LEFT JOIN fournisseurs f ON p.fournisseur_id=f.id
          WHERE $where
          ORDER BY p.nom
          LIMIT $perPage OFFSET $offset";
$stmt   = $db->prepare($query);
$stmt->execute($params);
$produits = $stmt->fetchAll();

layout_head('Médicaments', 'produits');
showFlash();
?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Liste des médicaments</div>
    <div class="flex gap-8">
      <form method="GET" style="display:flex;">
        <div class="search-box" style="min-width:420px;flex:1;max-width:640px;">
          <span style="color:var(--text3);display:flex;"><?= icon('search',14) ?></span>
          <input type="text" name="q" placeholder="Rechercher..." value="<?= e($q) ?>">
        </div>
      </form>
      <?php if (hasPermission('produits.ajouter')): ?>
      <a href="<?= url('produits', ['action'=>'add']) ?>" class="btn btn-primary btn-sm"><?= icon('plus',14) ?> Ajouter</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Médicament</th><th>Réf.</th><th>Catégorie</th>
          <th>Stock</th><th>P. Achat</th><th>P. Vente</th><th>Expiration</th>
          <th>Fournisseur</th><th>Statut</th>
          <?php if (hasPermission('produits.modifier') || hasPermission('produits.archiver')): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody id="produits-tbody">
        <?php foreach ($produits as $p):
          if     ($p['stock'] == 0)                   { $b='badge-red';   $t='Rupture';    }
          elseif ($p['stock'] <= $p['seuil_alerte'])  { $b='badge-gold';  $t='Stock bas';  }
          else                                         { $b='badge-green'; $t='Disponible'; }
        ?>
        <tr>
          <td class="td-name"><?= e($p['nom']) ?></td>
          <td class="td-mono"><?= e($p['reference'] ?? '—') ?></td>
          <td><span class="badge badge-gray"><?= e($p['cat'] ?? '—') ?></span></td>
          <td><strong><?= $p['stock'] ?></strong></td>
          <td class="fw-mono" style="color:var(--text3);"><?= fmtMoney((float)$p['prix_achat']) ?></td>
          <td class="fw-mono c-teal"><?= fmtMoney((float)$p['prix_vente']) ?></td>
          <td class="text-sm"><?= $p['date_expiration'] ? date('d/m/Y', strtotime($p['date_expiration'])) : '—' ?></td>
          <td class="text-sm"><?= e($p['fourn'] ?? '—') ?></td>
          <td><span class="badge <?= $b ?>"><?= $t ?></span></td>
          <?php if (hasPermission('produits.modifier') || hasPermission('produits.archiver')): ?>
          <td>
            <div class="flex gap-8">
              <a href="<?= url('produits', ['action'=>'edit','id'=>$p['id']], $p['nom'] ?? null) ?>" class="btn btn-ghost btn-xs"><?= icon('edit',13) ?></a>
              <button class="btn btn-danger btn-xs"
                onclick="confirmDeletePost('delete','<?= (int)$p['id'] ?>','Archiver ce médicament ?')">
                <?= icon('trash',13) ?>
              </button>
            </div>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (!$produits): ?>
        <tr><td colspan="9">
          <div class="empty">
            <div style="color:var(--text3);margin-bottom:8px;"><?= icon('pill',36) ?></div>
            <div>Aucun médicament trouvé</div>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= renderPagination($page, $perPage, $totalProduits, ['q'=>$q]) ?>
</div>
<?php layout_foot(); ?>
