<?php
$page_title = 'Produits';
$page_id = 'produits';
require_once '../includes/header.php';
requireAuth();
$db = getDB();

$msg = '';
// Traitement actions
if ($_SERVER['REQUEST_METHOD']==='POST' && canDo('produit_edit')) {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $ref  = trim($_POST['reference']);
        $nom  = trim($_POST['nom']);
        $desc = trim($_POST['description']);
        $cat  = $_POST['categorie_id'] ?: null;
        $four = $_POST['fournisseur_id'] ?: null;
        $pa   = (float)$_POST['prix_achat'];
        $pv   = (float)$_POST['prix_vente'];
        $qte  = (int)$_POST['quantite'];
        $min  = (int)$_POST['quantite_min'];
        $unit = trim($_POST['unite']);
        $id   = (int)($_POST['id'] ?? 0);
        if (!validateString($ref, 50, true) || !validateString($nom, 255, true)) {
            $msg = '<div class="alert alert-danger">La référence et le nom sont obligatoires.</div>';
        } elseif (!validateFloat($pa, 0) || !validateFloat($pv, 0)) {
            $msg = '<div class="alert alert-danger">Les prix doivent être positifs.</div>';
        } elseif (!validateInt($qte, 0) || !validateInt($min, 0)) {
            $msg = '<div class="alert alert-danger">Les quantités doivent être positives.</div>';
        } else {
            if ($id) {
                $db->prepare("UPDATE produits SET reference=?,nom=?,description=?,categorie_id=?,fournisseur_id=?,prix_achat=?,prix_vente=?,quantite=?,quantite_min=?,unite=? WHERE id=?")
                   ->execute([$ref,$nom,$desc,$cat,$four,$pa,$pv,$qte,$min,$unit,$id]);
                logAudit($db, 'update', 'produit', $id, ['nom'=>$nom,'reference'=>$ref]);
            } else {
                $db->prepare("INSERT INTO produits (reference,nom,description,categorie_id,fournisseur_id,prix_achat,prix_vente,quantite,quantite_min,unite) VALUES(?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$ref,$nom,$desc,$cat,$four,$pa,$pv,$qte,$min,$unit]);
                logAudit($db, 'create', 'produit', $db->lastInsertId(), ['nom'=>$nom,'reference'=>$ref]);
            }
            $msg = '<div class="alert alert-success"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Produit sauvegardé avec succès.</div>';
        }
    } elseif ($action === 'delete' && canDo('produit_delete')) {
        $del_id = (int)$_POST['id'];
        $db->prepare("UPDATE produits SET actif=0 WHERE id=?")->execute([$del_id]);
        logAudit($db, 'delete', 'produit', $del_id);
        $msg = '<div class="alert alert-success">Produit supprimé.</div>';
    }
}

// Filtres
$search = trim($_GET['s'] ?? '');
$cat_f  = (int)($_GET['cat'] ?? 0);
$page   = max(1,(int)($_GET['p'] ?? 1));
$per    = 15;

$where = "p.actif=1";
$params = [];
if ($search) { $where .= " AND (p.nom LIKE ? OR p.reference LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($cat_f)  { $where .= " AND p.categorie_id=?"; $params[] = $cat_f; }

$total = $db->prepare("SELECT COUNT(*) FROM produits p WHERE $where");
$total->execute($params);
$total = $total->fetchColumn();
$pages = ceil($total/$per);
$offset = ($page-1)*$per;

$st = $db->prepare("SELECT p.*,c.nom as cat_nom,c.couleur as cat_couleur,f.nom as four_nom FROM produits p LEFT JOIN categories c ON p.categorie_id=c.id LEFT JOIN fournisseurs f ON p.fournisseur_id=f.id WHERE $where ORDER BY p.created_at DESC LIMIT $per OFFSET $offset");
$st->execute($params);
$produits = $st->fetchAll();

$categories  = $db->query("SELECT * FROM categories ORDER BY nom")->fetchAll();
$fournisseurs= $db->query("SELECT * FROM fournisseurs WHERE actif=1 ORDER BY nom")->fetchAll();

$edit = null;
if (!empty($_GET['edit'])) {
    $edit = $db->prepare("SELECT * FROM produits WHERE id=?");
    $edit->execute([(int)$_GET['edit']]);
    $edit = $edit->fetch();
    if ($edit) echo '<script>document.addEventListener("DOMContentLoaded",()=>openModal("modal-produit"))</script>';
}
?>

<?= $msg ?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
  <form method="GET" style="display:flex;align-items:center;gap:10px;flex:1;flex-wrap:wrap">
    <div class="search-bar" style="flex:1;min-width:200px">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input class="form-control" type="text" name="s" placeholder="Rechercher produit, référence..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <select class="form-control form-select" name="cat" style="width:180px" onchange="this.form.submit()">
      <option value="">Toutes catégories</option>
      <?php foreach ($categories as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $cat_f==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-secondary btn-sm" type="submit">Filtrer</button>
    <?php if ($search||$cat_f): ?><a href="produits.php" class="btn btn-secondary btn-sm">×</a><?php endif; ?>
  </form>
  <?php if (canDo('produit_edit')): ?>
  <button class="btn btn-primary" onclick="openModal('modal-produit')">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Nouveau produit
  </button>
  <?php endif; ?>
</div>

<div class="card">
  <div style="overflow-x:auto">
    <?php if (empty($produits)): ?>
    <div class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
      <h4>Aucun produit trouvé</h4>
      <p>Ajoutez votre premier produit ou modifiez les filtres.</p>
    </div>
    <?php else: ?>
    <table>
      <thead><tr><th>Référence</th><th>Produit</th><th>Catégorie</th><th>Stock</th><th>P. Achat</th><th>P. Vente</th><th>Fournisseur</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($produits as $p): ?>
        <tr>
          <td><code style="background:var(--bg);padding:3px 8px;border-radius:6px;font-size:12px"><?= htmlspecialchars($p['reference']) ?></code></td>
          <td style="font-weight:600;max-width:200px"><?= htmlspecialchars($p['nom']) ?></td>
          <td>
            <?php if ($p['cat_nom']): ?>
            <span class="badge" style="background:<?= $p['cat_couleur']?>22;color:<?= $p['cat_couleur'] ?>"><?= htmlspecialchars($p['cat_nom']) ?></span>
            <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
          </td>
          <td>
            <?php
            $pct = $p['quantite_min']>0 ? min(100,round($p['quantite']/$p['quantite_min']*100)) : 100;
            $color = $p['quantite']==0 ? 'var(--danger)' : ($p['quantite']<=$p['quantite_min'] ? 'var(--warning)' : 'var(--success)');
            ?>
            <div style="min-width:90px">
              <div style="font-weight:700;color:<?= $color ?>;font-size:15px"><?= $p['quantite'] ?> <small style="font-weight:400;color:var(--muted);font-size:11px"><?= $p['unite'] ?></small></div>
              <div class="progress" style="margin-top:3px;height:4px">
                <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
              </div>
            </div>
          </td>
          <td style="color:var(--text-2)"><?= formatMoney($p['prix_achat']) ?></td>
          <td style="font-weight:600"><?= formatMoney($p['prix_vente']) ?></td>
          <td style="font-size:13px;color:var(--text-2)"><?= htmlspecialchars($p['four_nom'] ?? '—') ?></td>
          <td>
            <div style="display:flex;gap:4px">
              <?php if (canDo('mouvement_sortie') || canDo('mouvement_entree')): ?>
              <a href="mouvements.php?produit=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" title="Mouvement">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/></svg>
              </a>
              <?php endif; ?>
              <?php if (canDo('produit_edit')): ?>
              <a href="?edit=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" title="Modifier">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce produit ?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button class="btn btn-danger btn-sm" title="Supprimer">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php if ($pages > 1): ?>
  <div class="pagination">
    <span class="page-info"><?= $total ?> produit(s) — page <?= $page ?> / <?= $pages ?></span>
    <?php for ($i=max(1,$page-2); $i<=min($pages,$page+2); $i++): ?>
    <a href="?p=<?= $i ?>&s=<?= urlencode($search) ?>&cat=<?= $cat_f ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- MODAL PRODUIT -->
<?php if (canDo('produit_edit')): ?>
<div class="modal-bg" id="modal-produit">
  <div class="modal" style="max-width:640px">
    <div class="modal-header">
      <div class="modal-title"><?= $edit ? 'Modifier' : 'Nouveau' ?> produit</div>
      <button class="modal-close" onclick="closeModal('modal-produit')">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
      <div class="modal-body">
        <div class="form-row form-row-2">
          <div class="form-group">
            <label class="form-label">Référence *</label>
            <input class="form-control" name="reference" required value="<?= htmlspecialchars($edit['reference']??'') ?>" placeholder="EX: INFO-001">
          </div>
          <div class="form-group">
            <label class="form-label">Unité</label>
            <input class="form-control" name="unite" value="<?= htmlspecialchars($edit['unite']??'pcs') ?>" placeholder="pcs, kg, L...">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Nom du produit *</label>
          <input class="form-control" name="nom" required value="<?= htmlspecialchars($edit['nom']??'') ?>" placeholder="Nom complet du produit">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea class="form-control" name="description" rows="2" placeholder="Description optionnelle..."><?= htmlspecialchars($edit['description']??'') ?></textarea>
        </div>
        <div class="form-row form-row-2">
          <div class="form-group">
            <label class="form-label">Catégorie</label>
            <select class="form-control form-select" name="categorie_id">
              <option value="">— Aucune —</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($edit['categorie_id']??'')==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Fournisseur</label>
            <select class="form-control form-select" name="fournisseur_id">
              <option value="">— Aucun —</option>
              <?php foreach ($fournisseurs as $f): ?>
              <option value="<?= $f['id'] ?>" <?= ($edit['fournisseur_id']??'')==$f['id']?'selected':'' ?>><?= htmlspecialchars($f['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row form-row-3">
          <div class="form-group">
            <label class="form-label">Prix achat</label>
            <input class="form-control" type="number" name="prix_achat" step="1" value="<?= $edit['prix_achat']??0 ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Prix vente</label>
            <input class="form-control" type="number" name="prix_vente" step="1" value="<?= $edit['prix_vente']??0 ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Stock actuel</label>
            <input class="form-control" type="number" name="quantite" value="<?= $edit['quantite']??0 ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Quantité minimale (alerte)</label>
          <input class="form-control" type="number" name="quantite_min" value="<?= $edit['quantite_min']??5 ?>">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-produit')">Annuler</button>
        <button type="submit" class="btn btn-primary">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Enregistrer
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
