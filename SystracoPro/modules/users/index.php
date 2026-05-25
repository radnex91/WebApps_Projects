<?php // modules/users/index.php
require_once '../../includes/config.php'; requireLogin(); requirePerm('users.manage');
$pageTitle='Utilisateurs'; $aid=getUserAgenceId();

// ── Toggle actif/inactif ────────────────────────────────────────
if(isset($_GET['toggle'])){
    $pdo->prepare("UPDATE utilisateurs SET actif=NOT actif WHERE id=? AND id!=?")->execute([(int)$_GET['toggle'],$_SESSION['user_id']]);
    flash('Statut modifié.');redirect(BASE_URL.'modules/users/index.php');
}

// ── POST: créer / modifier utilisateur ──────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST'){
    $id=(int)($_POST['id']??0);
    $nom=trim($_POST['nom']??''); $prenom=trim($_POST['prenom']??''); $un=trim($_POST['username']??'');
    $email=trim($_POST['email']??''); $rid=(int)($_POST['role_id']??0); $pw=$_POST['password']??'';
    $col=trim($_POST['avatar_color']??'#2563eb');
    $agence_ids=array_map('intval',$_POST['agence_ids']??[]);

    if(!$nom||!$un||!$rid){flash('Nom, username et rôle obligatoires.','danger');}
    elseif(!$pw&&!$id){flash('Mot de passe requis pour la création.','danger');}
    else{
        try{
            $pdo->beginTransaction();
            // Backward compat : 1ère agence = agence_id, 2ème = agence_affectation_id
            $agence_principal=$agence_ids[0]??null;
            $agence_affectation=$agence_ids[1]??null;

            if($id){
                if($pw){
                    $pdo->prepare("UPDATE utilisateurs SET nom=?,prenom=?,username=?,email=?,role_id=?,agence_id=?,agence_affectation_id=?,avatar_color=?,password=? WHERE id=?")
                        ->execute([$nom,$prenom,$un,$email,$rid,$agence_principal,$agence_affectation,$col,password_hash($pw,PASSWORD_DEFAULT),$id]);
                }else{
                    $pdo->prepare("UPDATE utilisateurs SET nom=?,prenom=?,username=?,email=?,role_id=?,agence_id=?,agence_affectation_id=?,avatar_color=? WHERE id=?")
                        ->execute([$nom,$prenom,$un,$email,$rid,$agence_principal,$agence_affectation,$col,$id]);
                }
            }else{
                $pdo->prepare("INSERT INTO utilisateurs (username,password,nom,prenom,email,role_id,agence_id,agence_affectation_id,avatar_color) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$un,password_hash($pw,PASSWORD_DEFAULT),$nom,$prenom,$email,$rid,$agence_principal,$agence_affectation,$col]);
                $id=$pdo->lastInsertId();
            }

            // Synchroniser user_agences
            $pdo->prepare("DELETE FROM user_agences WHERE user_id=?")->execute([$id]);
            if(!empty($agence_ids)){
                $insUA=$pdo->prepare("INSERT INTO user_agences (user_id,agence_id,is_principal) VALUES (?,?,?)");
                foreach($agence_ids as $i=>$aid_){
                    $insUA->execute([$id,$aid_,$i===0?1:0]);
                }
            }

            $pdo->commit();
            // Rafraîchir la session si l'utilisateur modifié est celui connecté
            if($id===$_SESSION['user_id']){
                $fresh=$pdo->prepare("SELECT u.*,r.code as role_code,r.nom as role_nom,r.couleur as role_color,r.niveau FROM utilisateurs u JOIN roles r ON u.role_id=r.id WHERE u.id=?");
                $fresh->execute([$id]); $freshData=$fresh->fetch(PDO::FETCH_ASSOC);
                if($freshData){
                    $_SESSION['user']=$freshData;
                    $_SESSION['role_code']=$freshData['role_code'];
                    $_SESSION['role_nom']=$freshData['role_nom'];
                    $_SESSION['role_color']=$freshData['role_color'];
                    $perms=$pdo->prepare("SELECT p.code FROM role_permissions rp JOIN permissions p ON rp.permission_id=p.id WHERE rp.role_id=?");
                    $perms->execute([$freshData['role_id']]);
                    $_SESSION['permissions']=array_column($perms->fetchAll(),'code');
                }
            }
            logAction($pdo,'modifie_utilisateur','users',"Utilisateur $id modifié");
            flash($id&&$_POST['id']?'Utilisateur modifié.':'Utilisateur créé.');
        }catch(PDOException $e){
            $pdo->rollBack();
            if(strpos($e->getMessage(),'Duplicate')!==false){flash('Username déjà utilisé.','danger');}
            else{flash('Erreur : '.sanitize($e->getMessage()),'danger');}
        }
        redirect(BASE_URL.'modules/users/index.php');
    }
}

// ── Données ─────────────────────────────────────────────────────
$roles=$pdo->query("SELECT * FROM roles ORDER BY niveau DESC")->fetchAll();
$agences=$pdo->query("SELECT * FROM agences WHERE actif=1 ORDER BY nom")->fetchAll();
$wA=isAdmin()?'':($aid?"AND u.agence_id=$aid":'AND 1=0');
$users=$pdo->query("SELECT u.*,r.nom as role_nom,r.couleur as role_color FROM utilisateurs u JOIN roles r ON u.role_id=r.id LEFT JOIN agences a1 ON u.agence_id=a1.id WHERE 1=1 $wA ORDER BY u.created_at DESC")->fetchAll();

// Agences par utilisateur (pour affichage)
$uAgMap=[];
$uAgRows=$pdo->query("SELECT ua.user_id,ua.agence_id,ua.is_principal,a.ville,a.nom FROM user_agences ua JOIN agences a ON ua.agence_id=a.id ORDER BY ua.is_principal DESC,a.nom")->fetchAll();
foreach($uAgRows as $r) $uAgMap[$r['user_id']][]=$r;

include '../../includes/header.php'; ?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Utilisateurs</div>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-users-cog"></i> Utilisateurs (<?= count($users) ?>)</h3><button class="btn btn-primary btn-sm" onclick="resetUForm();openModal('um')"><i class="fas fa-plus"></i> Ajouter</button></div>
  <div class="card-body" style="padding:0;">
    <div class="table-wrap"><table>
      <thead><tr><th>Utilisateur</th><th>Username</th><th>Rôle</th><th>Agences</th><th>Dernière connexion</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($users as $u): ?>
        <tr>
          <td><div style="display:flex;align-items:center;gap:8px;"><div class="avatar avatar-sm" style="background:<?= sanitize($u['avatar_color']) ?>"><?= initials(($u['prenom']??'').' '.$u['nom']) ?></div><div><strong><?= sanitize($u['prenom'].' '.$u['nom']) ?></strong><div style="font-size:10px;color:var(--text3);"><?= sanitize($u['email']??'') ?></div></div></div></td>
          <td><code>@<?= sanitize($u['username']) ?></code></td>
          <td><span class="badge" style="background:<?= sanitize($u['role_color']) ?>20;color:<?= sanitize($u['role_color']) ?>"><?= sanitize($u['role_nom']) ?></span></td>
          <td>
            <?php foreach($uAgMap[$u['id']]??[] as $ua): ?>
            <span class="badge" style="margin:1px;background:<?= $ua['is_principal']?'#2563eb20;color:#2563eb':'#f59e0b20;color:#d97706' ?>;"><?= $ua['is_principal']?'★ ':'' ?><?= sanitize($ua['nom']) ?></span>
            <?php endforeach; ?>
            <?php if(empty($uAgMap[$u['id']??0])): ?><span style="color:var(--text3);">Siège</span><?php endif; ?>
          </td>
          <td style="font-size:11px;color:var(--text3);"><?= $u['last_login']?timeAgo($u['last_login']):'Jamais' ?></td>
          <td><?= $u['actif']?'<span class="badge badge-green">Actif</span>':'<span class="badge badge-red">Inactif</span>' ?></td>
          <?php $uData=$u; $uData['_agences']=array_map('intval',array_column($uAgMap[$u['id']]??[],'agence_id')); ?>
          <td><div style="display:flex;gap:3px;"><button class="btn btn-xs btn-warning" onclick='editU(<?= htmlspecialchars(json_encode($uData,ENT_QUOTES)) ?>)'><i class="fas fa-edit"></i></button><a href="?toggle=<?= $u['id'] ?>" class="btn btn-xs btn-ghost" onclick="return confirm('Changer le statut ?')"><i class="fas fa-toggle-<?php echo $u['actif']?'on text-success':'off'; ?>"></i></a></div></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($users)): ?><tr><td colspan="7" class="t-empty"><i class="fas fa-users-cog"></i> Aucun utilisateur</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<!-- MODAL UTILISATEUR -->
<div class="modal-over" id="um"><div class="modal modal-sm">
  <div class="modal-head"><h3><i class="fas fa-user-plus"></i> Utilisateur</h3><button class="modal-x" onclick="closeModal('um')">✕</button></div>
  <form method="POST" id="u-form">
      <?= csrfField() ?>
      <div class="modal-body">
    <input type="hidden" name="id" id="u-id">
    <div class="form-grid">
      <div class="fg"><label class="flbl">Prénom</label><input type="text" name="prenom" id="u-pn" class="fc"></div>
      <div class="fg"><label class="flbl">Nom <span class="freq">*</span></label><input type="text" name="nom" id="u-nm" class="fc" required></div>
      <div class="fg"><label class="flbl">Username <span class="freq">*</span></label><input type="text" name="username" id="u-un" class="fc" required></div>
      <div class="fg"><label class="flbl">Email</label><input type="email" name="email" id="u-em" class="fc"></div>
      <div class="fg"><label class="flbl">Rôle <span class="freq">*</span></label><select name="role_id" id="u-rid" class="fc" required><option value="">—</option><?php foreach($roles as $r): ?><option value="<?= $r['id'] ?>"><?= sanitize($r['nom']) ?></option><?php endforeach; ?></select></div>
      <div class="fg"><label class="flbl">Mot de passe</label><input type="password" name="password" id="u-pw" class="fc" placeholder="(inchangé si vide)"></div>
    </div>
    <div class="fg full" style="margin-top:8px;">
      <label class="flbl">Agences <span style="font-size:11px;color:var(--text3);font-weight:400;">— la 1ère cochée sera l'agence principale</span></label>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:6px;max-height:180px;overflow-y:auto;border:1.5px solid var(--border);border-radius:var(--radius);padding:10px;">
        <?php foreach($agences as $a): ?>
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;padding:2px 0;">
          <input type="checkbox" name="agence_ids[]" value="<?= $a['id'] ?>" class="u-agence-cb" data-aid="<?= $a['id'] ?>">
          <?= sanitize($a['nom']) ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="fg" style="margin-top:6px;">
      <label class="flbl">Couleur avatar</label><input type="color" name="avatar_color" id="u-col" class="fc" value="#2563eb" style="height:38px;cursor:pointer;">
    </div>
  </div>
  <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('um')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
  </form>
</div></div>

<script>
function resetUForm(){
  document.getElementById('u-id').value='';
  document.getElementById('u-pn').value='';
  document.getElementById('u-nm').value='';
  document.getElementById('u-un').value='';
  document.getElementById('u-em').value='';
  document.getElementById('u-rid').value='';
  document.getElementById('u-pw').value='';
  document.getElementById('u-pw').placeholder='';
  document.getElementById('u-col').value='#2563eb';
  document.querySelectorAll('.u-agence-cb').forEach(function(cb){cb.checked=false;});
}

function editU(u){
  document.getElementById('u-id').value=u.id;
  document.getElementById('u-nm').value=u.nom;
  document.getElementById('u-pn').value=u.prenom||'';
  document.getElementById('u-un').value=u.username;
  document.getElementById('u-em').value=u.email||'';
  document.getElementById('u-rid').value=u.role_id;
  document.getElementById('u-col').value=u.avatar_color||'#2563eb';
  document.getElementById('u-pw').placeholder='(inchangé)';
  var agences=u._agences||[];
  document.querySelectorAll('.u-agence-cb').forEach(function(cb){
    cb.checked=agences.indexOf(parseInt(cb.dataset.aid))!==-1;
  });
  openModal('um');
}
</script>
<?php include '../../includes/footer.php'; ?>