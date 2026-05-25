<?php // modules/roles/index.php
require_once '../../includes/config.php'; requireLogin(); requirePerm('permissions.manage');
$pageTitle = 'Rôles et Permissions';

// ── POST: sauvegarder un rôle ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_role') {
    $id=(int)($_POST['id']??0);
    $code=trim($_POST['code']??'');
    $nom=trim($_POST['nom']??'');
    $desc=trim($_POST['description']??'');
    $couleur=trim($_POST['couleur']??'#2563eb');
    $niveau=(int)($_POST['niveau']??1);
    if(!$code||!$nom){flash('Code et nom obligatoires.','danger');}
    else{
        try{
            if($id){
                $pdo->prepare("UPDATE roles SET code=?,nom=?,description=?,couleur=?,niveau=? WHERE id=?")->execute([$code,$nom,$desc,$couleur,$niveau,$id]);
                flash('Rôle modifié.');
            }else{
                $pdo->prepare("INSERT INTO roles (code,nom,description,couleur,niveau) VALUES (?,?,?,?,?)")->execute([$code,$nom,$desc,$couleur,$niveau]);
                $id=$pdo->lastInsertId();
                flash('Rôle créé.');
            }
        }catch(PDOException $e){flash('Code de rôle déjà utilisé.','danger');}
    }
    redirect(BASE_URL.'modules/roles/index.php?role_id='.$id);
}

// ── POST: sauvegarder les permissions d'un rôle ────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_permissions') {
    $role_id=(int)($_POST['role_id']??0);
    $perm_ids=$_POST['permission_ids']??[];
    if(!$role_id){flash('Rôle invalide.','danger');redirect(BASE_URL.'modules/roles/');}

    // Anti-lockout : ne jamais retirer permissions.manage de son propre rôle
    $permManageId=$pdo->query("SELECT id FROM permissions WHERE code='permissions.manage'")->fetchColumn();
    if($role_id===$_SESSION['user']['role_id'] && !in_array($permManageId,$perm_ids)){
        $perm_ids[]=$permManageId;
        flash('Permission « Gérer les rôles et permissions » conservée sur votre rôle.','warning');
    }

    $pdo->beginTransaction();
    try{
        $pdo->prepare("DELETE FROM role_permissions WHERE role_id=?")->execute([$role_id]);
        $ins=$pdo->prepare("INSERT INTO role_permissions (role_id,permission_id) VALUES (?,?)");
        foreach($perm_ids as $pid) $ins->execute([$role_id,(int)$pid]);
        $pdo->commit();
        flash('Permissions mises à jour.');
    }catch(Exception $e){$pdo->rollBack();flash('Erreur lors de la sauvegarde.','danger');}
    redirect(BASE_URL.'modules/roles/index.php?role_id='.$role_id);
}

// ── Données ─────────────────────────────────────────────────────
$roles=$pdo->query("SELECT r.*,(SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id=r.id) AS nb_perms,(SELECT COUNT(*) FROM utilisateurs u WHERE u.role_id=r.id) AS nb_users FROM roles r ORDER BY r.niveau DESC")->fetchAll();
$permissions=$pdo->query("SELECT * FROM permissions ORDER BY module,nom")->fetchAll();
$permByModule=[];
foreach($permissions as $p) $permByModule[$p['module']][]=$p;

$selectedRoleId=(int)($_GET['role_id']??0);
$rolePerms=[];
if($selectedRoleId){
    $s=$pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id=?");$s->execute([$selectedRoleId]);
    $rolePerms=array_map('intval',array_column($s->fetchAll(),'permission_id'));
}

$roleUsers=[];
if($selectedRoleId){
    $roleUsers=$pdo->prepare("SELECT u.id,u.nom,u.prenom,u.username,u.actif,a.ville AS agence_ville FROM utilisateurs u LEFT JOIN agences a ON u.agence_id=a.id WHERE u.role_id=? ORDER BY u.nom");$roleUsers->execute([$selectedRoleId]);
    $roleUsers=$roleUsers->fetchAll();
}

include '../../includes/header.php'; ?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Rôles et Permissions</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
<!-- LISTE DES RÔLES -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-shield-alt"></i> Rôles (<?= count($roles) ?>)</h3><button class="btn btn-primary btn-sm" onclick="resetRoleForm();openModal('role-modal')"><i class="fas fa-plus"></i> Ajouter</button></div>
  <div class="card-body" style="padding:0;">
    <div class="table-wrap"><table>
      <thead><tr><th>Rôle</th><th>Code</th><th>Niveau</th><th>Permissions</th><th>Utilisateurs</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($roles as $r): ?>
        <tr class="<?= $selectedRoleId===$r['id']?'row-selected':'' ?>" style="cursor:pointer;" onclick="location.href='?role_id=<?= $r['id'] ?>'">
          <td><div style="display:flex;align-items:center;gap:8px;"><span style="width:14px;height:14px;border-radius:50%;background:<?= sanitize($r['couleur']) ?>;flex-shrink:0;"></span><strong><?= sanitize($r['nom']) ?></strong></div></td>
          <td><code style="font-size:11px;"><?= sanitize($r['code']) ?></code></td>
          <td style="text-align:center;"><?= $r['niveau'] ?></td>
          <td style="text-align:center;"><span class="badge badge-blue"><?= $r['nb_perms'] ?></span></td>
          <td style="text-align:center;"><?= $r['nb_users'] ?></td>
          <td><button class="btn btn-xs btn-warning" onclick="event.stopPropagation();editRole(<?= htmlspecialchars(json_encode($r, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)" title="Modifier"><i class="fas fa-edit"></i></button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>

<?php if($selectedRoleId):
$selRole=null;
foreach($roles as $r) if($r['id']===$selectedRoleId){$selRole=$r;break;}
if($selRole): ?>
<!-- PERMISSIONS DU RÔLE SÉLECTIONNÉ -->
<div>
  <div class="card" style="margin-bottom:16px;">
    <div class="card-header"><h3><i class="fas fa-key"></i> Permissions — <span style="color:<?= sanitize($selRole['couleur']) ?>"><?= sanitize($selRole['nom']) ?></span></h3></div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_permissions">
      <input type="hidden" name="role_id" value="<?= $selectedRoleId ?>">
      <div class="card-body" style="padding:12px 16px;">
        <?php foreach($permByModule as $mod=>$perms): ?>
        <div style="margin-bottom:12px;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
            <strong style="font-size:12px;text-transform:uppercase;color:var(--text3);"><?= sanitize($mod) ?></strong>
            <label style="font-size:11px;cursor:pointer;color:var(--primary);"><input type="checkbox" class="mod-toggle" data-module="<?= sanitize($mod) ?>" onchange="toggleModule('<?= sanitize($mod) ?>',this.checked)"> Tout cocher</label>
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:6px 16px;">
            <?php foreach($perms as $p): ?>
            <label style="display:flex;align-items:center;gap:5px;font-size:13px;cursor:pointer;padding:3px 0;">
              <input type="checkbox" name="permission_ids[]" value="<?= $p['id'] ?>" class="perm-cb mod-<?= sanitize($mod) ?>" <?= in_array($p['id'],$rolePerms)?'checked':'' ?>>
              <?= sanitize($p['nom']) ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="location.href='?'">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer les permissions</button></div>
    </form>
  </div>

  <?php if($roleUsers): ?>
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-users"></i> Utilisateurs avec ce rôle (<?= count($roleUsers) ?>)</h3></div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrap"><table>
        <thead><tr><th>Nom</th><th>Username</th><th>Agence</th><th>Statut</th></tr></thead>
        <tbody>
          <?php foreach($roleUsers as $u): ?>
          <tr>
            <td><strong><?= sanitize(($u['prenom']??'').' '.$u['nom']) ?></strong></td>
            <td><code>@<?= sanitize($u['username']) ?></code></td>
            <td><?= sanitize($u['agence_ville']??'Siège') ?></td>
            <td><?= $u['actif']?'<span class="badge badge-green">Actif</span>':'<span class="badge badge-red">Inactif</span>' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; endif; ?>
</div>

<!-- MODAL CRÉER / MODIFIER RÔLE -->
<div class="modal-over" id="role-modal">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-shield-alt"></i> Rôle</h3><button class="modal-x" onclick="closeModal('role-modal')">✕</button></div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_role">
      <input type="hidden" name="id" id="r-id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="fg"><label class="flbl">Code <span class="freq">*</span></label><input type="text" name="code" id="r-code" class="fc" required placeholder="ex: chef_parc"></div>
          <div class="fg"><label class="flbl">Nom <span class="freq">*</span></label><input type="text" name="nom" id="r-nom" class="fc" required></div>
          <div class="fg full"><label class="flbl">Description</label><textarea name="description" id="r-desc" class="fc" rows="2"></textarea></div>
          <div class="fg"><label class="flbl">Couleur</label><input type="color" name="couleur" id="r-col" class="fc" value="#2563eb" style="height:38px;cursor:pointer;"></div>
          <div class="fg"><label class="flbl">Niveau</label><input type="number" name="niveau" id="r-niv" class="fc" min="1" max="10" value="1"></div>
        </div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('role-modal')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
    </form>
  </div>
</div>

<style>.row-selected{background:var(--primary-light,rgba(37,99,235,0.08))!important;}</style>

<script>
function resetRoleForm(){
  document.getElementById('r-id').value='';
  document.getElementById('r-code').value='';
  document.getElementById('r-nom').value='';
  document.getElementById('r-desc').value='';
  document.getElementById('r-col').value='#2563eb';
  document.getElementById('r-niv').value='1';
}
function editRole(r){
  document.getElementById('r-id').value=r.id;
  document.getElementById('r-code').value=r.code;
  document.getElementById('r-nom').value=r.nom;
  document.getElementById('r-desc').value=r.description||'';
  document.getElementById('r-col').value=r.couleur||'#2563eb';
  document.getElementById('r-niv').value=r.niveau||1;
  openModal('role-modal');
}
function toggleModule(mod,checked){
  document.querySelectorAll('.mod-'+mod).forEach(function(cb){cb.checked=checked;});
}
</script>
<?php include '../../includes/footer.php'; ?>