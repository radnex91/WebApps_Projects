<?php
$page_title = 'Catégories';
$page_id = 'categories';
require_once '../includes/header.php';
requireAuth();
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && canDo('categorie_edit')) {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $nom  = trim($_POST['nom']);
        $desc = trim($_POST['description']);
        $coul = $_POST['couleur'];
        $id   = (int)($_POST['id']??0);
        if (!validateString($nom, 255, true)) {
            $msg = '<div class="alert alert-danger">Le nom est obligatoire.</div>';
        } elseif (!validateColor($coul)) {
            $msg = '<div class="alert alert-danger">Couleur invalide.</div>';
        } else {
            if ($id) {
                $db->prepare("UPDATE categories SET nom=?,description=?,couleur=? WHERE id=?")->execute([$nom,$desc,$coul,$id]);
                logAudit($db, 'update', 'categorie', $id, ['nom'=>$nom]);
            } else {
                $db->prepare("INSERT INTO categories (nom,description,couleur) VALUES(?,?,?)")->execute([$nom,$desc,$coul]);
                logAudit($db, 'create', 'categorie', $db->lastInsertId(), ['nom'=>$nom]);
            }
            $msg='<div class="alert alert-success">Catégorie sauvegardée.</div>';
        }
    } elseif ($action==='delete') {
        $del_id = (int)$_POST['id'];
        $db->prepare("UPDATE categories SET actif=0 WHERE id=?")->execute([$del_id]);
        logAudit($db, 'delete', 'categorie', $del_id);
        $msg='<div class="alert alert-success">Catégorie supprimée.</div>';
    }
}

$categories = $db->query("SELECT c.*,(SELECT COUNT(*) FROM produits p WHERE p.categorie_id=c.id AND p.actif=1) as nb_produits FROM categories c WHERE c.actif=1 ORDER BY nom")->fetchAll();
$edit=null;
if (!empty($_GET['edit'])) { $st=$db->prepare("SELECT * FROM categories WHERE id=?"); $st->execute([(int)$_GET['edit']]); $edit=$st->fetch(); if($edit) echo '<script>document.addEventListener("DOMContentLoaded",()=>openModal("modal-cat"))</script>'; }
?>
<?= $msg ?>
<div style="margin-bottom:20px;display:flex;justify-content:flex-end">
  <?php if (canDo('categorie_edit')): ?>
  <button class="btn btn-primary" onclick="openModal('modal-cat')">+ Nouvelle catégorie</button>
  <?php endif; ?>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px">
  <?php foreach ($categories as $c): ?>
  <div class="card" style="padding:20px">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
      <div style="width:42px;height:42px;border-radius:12px;background:<?= $c['couleur'] ?>22;display:flex;align-items:center;justify-content:center;font-size:18px">
        📁
      </div>
      <div style="flex:1">
        <div style="font-family:'Manrope',sans-serif;font-weight:700;font-size:15px"><?= htmlspecialchars($c['nom']) ?></div>
        <div style="font-size:12px;color:var(--muted)"><?= $c['nb_produits'] ?> produit(s)</div>
      </div>
      <div style="width:12px;height:12px;border-radius:50%;background:<?= $c['couleur'] ?>"></div>
    </div>
    <?php if ($c['description']): ?><p style="font-size:13px;color:var(--text-2);margin-bottom:12px"><?= htmlspecialchars($c['description']) ?></p><?php endif; ?>
    <?php if (canDo('categorie_edit')): ?>
    <div style="display:flex;gap:6px">
      <a href="?edit=<?= $c['id'] ?>" class="btn btn-secondary btn-sm" style="flex:1;justify-content:center">Modifier</a>
      <form method="POST" onsubmit="return confirm('Supprimer ?')">
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><?= csrf_field() ?>
        <button class="btn btn-danger btn-sm">🗑</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if (empty($categories)): ?><div class="card"><div class="empty-state"><p>Aucune catégorie.</p></div></div><?php endif; ?>
</div>

<?php if (canDo('categorie_edit')): ?>
<div class="modal-bg" id="modal-cat">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <div class="modal-title"><?= $edit?'Modifier':'Nouvelle' ?> catégorie</div>
      <button class="modal-close" onclick="closeModal('modal-cat')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <form method="POST"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit['id']??'' ?>"><?= csrf_field() ?>
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Nom *</label><input class="form-control" name="nom" required value="<?= htmlspecialchars($edit['nom']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"><?= htmlspecialchars($edit['description']??'') ?></textarea></div>
        <div class="form-group"><label class="form-label">Couleur</label><input type="color" name="couleur" value="<?= $edit['couleur']??'#6366f1' ?>" style="width:60px;height:40px;border:1px solid var(--border);border-radius:8px;cursor:pointer;padding:2px"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modal-cat')">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php require_once '../includes/footer.php'; ?>
