<?php
// modules/users/index.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('users.manage');
$pageTitle = 'Gestion des Utilisateurs';

// DELETE
if(isset($_GET['del']) && isSuperAdmin()){
    $did=(int)$_GET['del'];
    if($did!==$_SESSION['user_id']){
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$did]);
        flash('Utilisateur supprimé.','warning');
    } else { flash('Impossible de supprimer votre propre compte.','danger'); }
    redirect(BASE_URL.'modules/users/');
}
// TOGGLE
if(isset($_GET['toggle'])){
    $tid=(int)$_GET['toggle'];
    $pdo->prepare("UPDATE users SET actif=NOT actif WHERE id=? AND id!=?")->execute([$tid,$_SESSION['user_id']]);
    flash('Statut modifié.');
    redirect(BASE_URL.'modules/users/');
}

$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$search = trim($_GET['q']??'');
$roleF  = (int)($_GET['role']??0);

$where=['1=1']; $params=[];
if($search){ $where[]="(u.username LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)"; $params=array_merge($params,["%$search%","%$search%","%$search%","%$search%"]); }
if($roleF){ $where[]="u.role_id=?"; $params[]=$roleF; }
$ws=implode(' AND ',$where);

$total=$pdo->prepare("SELECT COUNT(*) FROM users u WHERE $ws"); $total->execute($params);
$totalRows=$total->fetchColumn();

$stmt=$pdo->prepare("SELECT u.*,r.nom as role_nom,r.couleur as role_color,r.code as role_code FROM users u JOIN roles r ON u.role_id=r.id WHERE $ws ORDER BY u.created_at DESC");
$stmt->execute($params);
$users=$stmt->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span> Utilisateurs</div>

<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-users-cog"></i> Utilisateurs (<?= $totalRows ?>)</h3>
    <button class="btn btn-primary btn-sm" onclick="openModal('user-modal')"><i class="fas fa-plus"></i> Nouvel utilisateur</button>
  </div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <input type="text" name="q" class="form-control" placeholder="🔍 Nom, username, email..." value="<?= sanitize($search) ?>" style="max-width:280px;">
      <select name="role" class="form-control" style="max-width:180px;">
        <option value="">Tous les rôles</option>
        <?php foreach($roles as $r): ?>
        <option value="<?= $r['id'] ?>" <?= $roleF==$r['id']?'selected':'' ?>><?= sanitize($r['nom']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-filter"></i></button>
      <a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i></a>
    </form>
    <div class="table-wrap">
    <table>
      <thead><tr><th>Utilisateur</th><th>Email</th><th>Rôle</th><th>Dernière connexion</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($users as $u): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div class="avatar" style="background:<?= sanitize($u['avatar_color']) ?>;"><?= initials(($u['prenom']??'').' '.$u['nom']) ?></div>
              <div>
                <div style="font-weight:600;"><?= sanitize($u['prenom'].' '.$u['nom']) ?></div>
                <div style="font-size:11px;color:var(--text3);">@<?= sanitize($u['username']) ?></div>
              </div>
            </div>
          </td>
          <td><?= sanitize($u['email']??'—') ?></td>
          <td>
            <span class="badge" style="background:<?= sanitize($u['role_color']) ?>20;color:<?= sanitize($u['role_color']) ?>;">
              <?= sanitize($u['role_nom']) ?>
            </span>
          </td>
          <td style="font-size:12px;color:var(--text3);"><?= $u['last_login']?timeAgo($u['last_login']):'Jamais' ?></td>
          <td><?= $u['actif']?'<span class="badge badge-success">Actif</span>':'<span class="badge badge-danger">Inactif</span>' ?></td>
          <td>
            <div style="display:flex;gap:4px;">
              <button class="btn btn-sm btn-secondary" onclick="editUser(<?= htmlspecialchars(json_encode($u),ENT_QUOTES) ?>)" title="Modifier"><i class="fas fa-edit"></i></button>
              <a href="?toggle=<?= $u['id'] ?>" class="btn btn-sm btn-warning" title="<?= $u['actif']?'Désactiver':'Activer' ?>"><i class="fas fa-<?= $u['actif']?'ban':'check' ?>"></i></a>
              <?php if(isSuperAdmin() && $u['id']!=$_SESSION['user_id']): ?>
              <a href="?del=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cet utilisateur ?')" title="Supprimer"><i class="fas fa-trash"></i></a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($users)): ?><tr><td colspan="6"><div class="table-empty"><i class="fas fa-users"></i>Aucun utilisateur trouvé</div></td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<!-- PERMISSIONS PAR RÔLE -->
<div class="card" style="margin-top:20px;">
  <div class="card-header"><h3><i class="fas fa-shield-alt"></i> Aperçu des permissions par rôle</h3></div>
  <div class="card-body">
    <div class="table-wrap">
    <?php
    $allPerms=$pdo->query("SELECT * FROM permissions ORDER BY module,nom")->fetchAll();
    $rolePerms=[];
    foreach($roles as $r){
        $rp=$pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id=?");
        $rp->execute([$r['id']]);
        $rolePerms[$r['id']]=array_column($rp->fetchAll(),'permission_id');
    }
    ?>
    <table style="font-size:11px;">
      <thead>
        <tr>
          <th>Permission</th>
          <?php foreach($roles as $r): ?>
          <th style="text-align:center;min-width:80px;">
            <span style="display:inline-block;padding:2px 6px;border-radius:4px;background:<?= sanitize($r['couleur']) ?>20;color:<?= sanitize($r['couleur']) ?>;font-size:10px;"><?= sanitize($r['nom']) ?></span>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php $lastMod=''; foreach($allPerms as $p):
          if($lastMod!==$p['module']){ $lastMod=$p['module']; ?>
          <tr><td colspan="<?= count($roles)+1 ?>" style="background:var(--bg2);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.5px;padding:5px 10px;color:var(--text2);"><?= sanitize($p['module']) ?></td></tr>
          <?php } ?>
        <tr>
          <td style="padding:5px 10px;"><?= sanitize($p['nom']) ?></td>
          <?php foreach($roles as $r): ?>
          <td style="text-align:center;padding:5px;">
            <?php if(isSuperAdmin()): ?>
            <form method="POST" action="<?= BASE_URL ?>modules/users/perm_toggle.php" style="display:inline;">
              <input type="hidden" name="role_id" value="<?= $r['id'] ?>">
              <input type="hidden" name="perm_id" value="<?= $p['id'] ?>">
              <button type="submit" style="background:none;border:none;cursor:pointer;font-size:14px;" title="Cliquer pour changer">
                <?= in_array($p['id'],$rolePerms[$r['id']]??[]) ? '✅' : '⬜' ?>
              </button>
            </form>
            <?php else: ?>
            <?= in_array($p['id'],$rolePerms[$r['id']]??[]) ? '✅' : '⬜' ?>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<!-- MODAL UTILISATEUR -->
<div class="modal-overlay" id="user-modal">
  <div class="modal modal-sm">
    <div class="modal-head">
      <h3 id="user-modal-title"><i class="fas fa-user-plus"></i> Nouvel utilisateur</h3>
      <button class="modal-close" onclick="closeModal('user-modal')">✕</button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>modules/users/save.php">
      <div class="modal-body">
        <input type="hidden" name="id" id="u-id">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Prénom</label>
            <input type="text" name="prenom" id="u-prenom" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Nom <span class="form-required">*</span></label>
            <input type="text" name="nom" id="u-nom" class="form-control" required>
          </div>
        </div>
        <div class="form-group" style="margin-top:10px;">
          <label class="form-label">Username <span class="form-required">*</span></label>
          <input type="text" name="username" id="u-username" class="form-control" required>
        </div>
        <div class="form-group" style="margin-top:10px;">
          <label class="form-label">Email</label>
          <input type="email" name="email" id="u-email" class="form-control">
        </div>
        <div class="form-group" style="margin-top:10px;">
          <label class="form-label">Rôle <span class="form-required">*</span></label>
          <select name="role_id" id="u-role" class="form-control" required>
            <?php foreach($roles as $r): ?>
            <option value="<?= $r['id'] ?>"><?= sanitize($r['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin-top:10px;">
          <label class="form-label">Mot de passe <span class="form-required" id="pw-req">*</span></label>
          <input type="password" name="password" id="u-password" class="form-control" placeholder="Laisser vide pour ne pas changer">
          <div class="form-hint" id="pw-hint">Minimum 6 caractères</div>
        </div>
        <div class="form-group" style="margin-top:10px;">
          <label class="form-label">Couleur avatar</label>
          <input type="color" name="avatar_color" id="u-color" class="form-control" value="#2563eb" style="height:40px;cursor:pointer;">
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-secondary" onclick="closeModal('user-modal')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
function editUser(u) {
  document.getElementById('u-id').value       = u.id;
  document.getElementById('u-nom').value      = u.nom;
  document.getElementById('u-prenom').value   = u.prenom||'';
  document.getElementById('u-username').value = u.username;
  document.getElementById('u-email').value    = u.email||'';
  document.getElementById('u-role').value     = u.role_id;
  document.getElementById('u-color').value    = u.avatar_color||'#2563eb';
  document.getElementById('u-password').placeholder = '(inchangé)';
  document.getElementById('pw-req').style.display='none';
  document.getElementById('user-modal-title').innerHTML = '<i class="fas fa-edit"></i> Modifier l\'utilisateur';
  openModal('user-modal');
}
function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
</script>

<?php include '../../includes/footer.php'; ?>
