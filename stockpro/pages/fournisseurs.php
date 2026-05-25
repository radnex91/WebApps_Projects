<?php
$page_title = 'Fournisseurs';
$page_id = 'fournisseurs';
require_once '../includes/header.php';
requireAuth();
$db = getDB();
$msg='';

if ($_SERVER['REQUEST_METHOD']==='POST' && canDo('fournisseur_edit')) {
    csrf_verify();
    $a=$_POST['action']??'';
    if ($a==='save') {
        $nom=trim($_POST['nom']); $contact=trim($_POST['contact']); $tel=trim($_POST['telephone']); $email=trim($_POST['email']); $adresse=trim($_POST['adresse']); $id=(int)($_POST['id']??0);
        if (!validateString($nom, 150, true)) {
            $msg='<div class="alert alert-danger">Le nom est obligatoire.</div>';
        } elseif ($email && !validateEmail($email)) {
            $msg='<div class="alert alert-danger">Email invalide.</div>';
        } elseif ($tel && !validatePhone($tel)) {
            $msg='<div class="alert alert-danger">Téléphone invalide.</div>';
        } else {
            if ($id) {
                $db->prepare("UPDATE fournisseurs SET nom=?,contact=?,telephone=?,email=?,adresse=? WHERE id=?")->execute([$nom,$contact,$tel,$email,$adresse,$id]);
                logAudit($db, 'update', 'fournisseur', $id, ['nom'=>$nom]);
            } else {
                $db->prepare("INSERT INTO fournisseurs (nom,contact,telephone,email,adresse) VALUES(?,?,?,?,?)")->execute([$nom,$contact,$tel,$email,$adresse]);
                logAudit($db, 'create', 'fournisseur', $db->lastInsertId(), ['nom'=>$nom]);
            }
            $msg='<div class="alert alert-success">Fournisseur sauvegardé.</div>';
        }
    } elseif ($a==='delete') { $db->prepare("UPDATE fournisseurs SET actif=0 WHERE id=?")->execute([(int)$_POST['id']]); logAudit($db, 'delete', 'fournisseur', (int)$_POST['id']); $msg='<div class="alert alert-success">Fournisseur supprimé.</div>'; }
}

$fournisseurs=$db->query("SELECT f.*,(SELECT COUNT(*) FROM produits p WHERE p.fournisseur_id=f.id AND p.actif=1) as nb FROM fournisseurs f WHERE f.actif=1 ORDER BY nom")->fetchAll();
$edit=null;
if (!empty($_GET['edit'])) { $st=$db->prepare("SELECT * FROM fournisseurs WHERE id=?"); $st->execute([(int)$_GET['edit']]); $edit=$st->fetch(); if($edit) echo '<script>document.addEventListener("DOMContentLoaded",()=>openModal("modal-four"))</script>'; }
?>
<?= $msg ?>
<div style="margin-bottom:20px;display:flex;justify-content:flex-end">
  <?php if (canDo('fournisseur_edit')): ?><button class="btn btn-primary" onclick="openModal('modal-four')">+ Nouveau fournisseur</button><?php endif; ?>
</div>
<div class="card">
  <div style="overflow-x:auto">
    <table>
      <thead><tr><th>Fournisseur</th><th>Contact</th><th>Téléphone</th><th>Email</th><th>Produits</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($fournisseurs as $f): ?>
      <tr>
        <td style="font-weight:600"><?= htmlspecialchars($f['nom']) ?></td>
        <td style="font-size:13px"><?= htmlspecialchars($f['contact']??'—') ?></td>
        <td style="font-size:13px"><?= htmlspecialchars($f['telephone']??'—') ?></td>
        <td style="font-size:13px;color:var(--primary)"><?= htmlspecialchars($f['email']??'—') ?></td>
        <td><span class="badge badge-blue"><?= $f['nb'] ?> produit(s)</span></td>
        <td>
          <div style="display:flex;gap:4px">
            <?php if (canDo('fournisseur_edit')): ?>
            <a href="?edit=<?= $f['id'] ?>" class="btn btn-secondary btn-sm">Modifier</a>
            <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $f['id'] ?>"><?= csrf_field() ?>
              <button class="btn btn-danger btn-sm">🗑</button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($fournisseurs)): ?><tr><td colspan="6"><div class="empty-state"><p>Aucun fournisseur.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (canDo('fournisseur_edit')): ?>
<div class="modal-bg" id="modal-four">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><?= $edit?'Modifier':'Nouveau' ?> fournisseur</div>
      <button class="modal-close" onclick="closeModal('modal-four')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <form method="POST"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit['id']??'' ?>"><?= csrf_field() ?>
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Nom *</label><input class="form-control" name="nom" required value="<?= htmlspecialchars($edit['nom']??'') ?>"></div>
        <div class="form-row form-row-2">
          <div class="form-group"><label class="form-label">Personne contact</label><input class="form-control" name="contact" value="<?= htmlspecialchars($edit['contact']??'') ?>"></div>
          <div class="form-group"><label class="form-label">Téléphone</label><input class="form-control" name="telephone" value="<?= htmlspecialchars($edit['telephone']??'') ?>"></div>
        </div>
        <div class="form-group"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= htmlspecialchars($edit['email']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Adresse</label><textarea class="form-control" name="adresse" rows="2"><?= htmlspecialchars($edit['adresse']??'') ?></textarea></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modal-four')">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php require_once '../includes/footer.php'; ?>
