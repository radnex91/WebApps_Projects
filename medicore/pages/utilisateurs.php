<?php
$currentPage = 'utilisateurs';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// Lire les roles directement depuis la BDD (pas de cache)
$rolesMap = [];
try {
    $__rows = getDB()->query("SELECT role, label, couleur, description FROM roles_config WHERE actif=1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($__rows as $__r) $rolesMap[$__r['role']] = $__r;
} catch (Exception $__e) { _log_error('UTILISATEURS', 'Echec lecture roles_config', __FILE__, __LINE__, $__e); }
if (empty($rolesMap)) {
    $rolesMap = [
        'admin'      => ['label'=>'Administrateur', 'couleur'=>'#ef4444'],
        'medecin'    => ['label'=>'Médecin',         'couleur'=>'#3b82f6'],
        'infirmier'  => ['label'=>'Infirmier(ère)',  'couleur'=>'#10b981'],
        'pharmacien' => ['label'=>'Pharmacien',      'couleur'=>'#8b5cf6'],
        'comptable'  => ['label'=>'Comptable',       'couleur'=>'#f59e0b'],
    ];
}

if (!hasRole(['admin'])) {
    require_once __DIR__ . '/../includes/layout.php';
    echo '<div class="alert alert-red">Accès réservé aux administrateurs.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$msg = ''; $msgType = '';

//  CRÉER UTILISATEUR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'create_user') {
    csrf_verify();
    // Valider le role directement depuis la BDD (pas de fallback qui crase les roles perso)
    $rolePost = post_str('role');
    try {
        $roleValid = (bool)db_scalar("SELECT COUNT(*) FROM roles_config WHERE role=? AND actif=1", [$rolePost]);
    } catch (Exception $__e) {
        $roleValid = in_array($rolePost, ['admin','medecin','infirmier','pharmacien','comptable']);
    }
    if (!$roleValid) { $msg = 'Rôle invalide ou inexistant.'; $msgType = 'red'; }
    $v = (new Validator())
        ->required('prenom', 'Prénom')->required('nom', 'Nom')
        ->email('email', 'Email')
        ->min_length('password', 8, 'Mot de passe')
        ->whitelist('statut', ['actif','inactif'], 'Statut');
    if (!$roleValid) {
        // Rôle invalide, msg déjà défini ci-dessus
    } elseif (!$v->passes()) {
        $msg = $v->first_error(); $msgType = 'red';
    } else {
        $exists = db_scalar("SELECT COUNT(*) FROM utilisateurs WHERE email=?", [post_email('email')]);
        if ($exists) { $msg = 'Email déjà utilisé.'; $msgType = 'red'; }
        else {
        $init = strtoupper(mb_substr($v->get('prenom'),0,1).mb_substr($v->get('nom'),0,1));
        db_exec(
            "INSERT INTO utilisateurs (nom,prenom,email,mot_de_passe,role,specialite,telephone,extension,statut,planning,avatar_initiales) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [$v->get('nom'),$v->get('prenom'),post_email('email'),password_hash($_POST['password'],PASSWORD_BCRYPT,['cost'=>12]),
             $rolePost,post_str('specialite'),post_str('telephone'),post_str('extension'),$v->get('statut'),post_str('planning'),$init]
        );
        invalidate_roles_cache();
        logActivity('Utilisateur créé: '.$v->get('prenom').' '.$v->get('nom').' ('.$rolePost.')', 'green', 'utilisateur');
        $msg = 'Utilisateur créé avec succès.'; $msgType = 'green';
        } // end else email
    } // end if/elseif/else roleValid
}

//  MODIFIER UTILISATEUR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'update_user') {
    csrf_verify();
    $id = post_int('user_id');
    if ($id <= 0) { $msg = 'ID invalide.'; $msgType = 'red'; }
    else {
        assert_owns('utilisateurs', $id);
        $isSelf = $id === (int)($_SESSION['user_id'] ?? 0);
        // Valider le rôle directement BDD pour accepter les rôles personnalisés
        $rolePostU = post_str('role');
        // Valider le role: directement en BDD, avec fallback si table absente
        try {
            $roleValidU = $isSelf || (bool)db_scalar("SELECT COUNT(*) FROM roles_config WHERE role=? AND actif=1", [$rolePostU]);
        } catch (Exception $__e) {
            // Table roles_config absente -> accepter les rôles de base
            $roleValidU = $isSelf || in_array($rolePostU, ['admin','medecin','infirmier','pharmacien','comptable']);
        }
        $v = (new Validator())
            ->required('prenom','Prénom')->required('nom','Nom')->email('email','Email')
            ->whitelist('statut',['actif','inactif','conge'],'Statut');
        if (!$roleValidU) { $msg = 'Rôle invalide ou inexistant.'; $msgType = 'red'; }
        elseif (!$v->passes()) { $msg = $v->first_error(); $msgType = 'red'; }
        else {
            $exists = db_scalar("SELECT COUNT(*) FROM utilisateurs WHERE email=? AND id!=?", [post_email('email'), $id]);
            if ($exists) { $msg = 'Cet email est déjà utilisé.'; $msgType = 'red'; }
            else {
                $init  = strtoupper(mb_substr($v->get('prenom'),0,1).mb_substr($v->get('nom'),0,1));
                $role  = $isSelf
                    ? db_scalar("SELECT role FROM utilisateurs WHERE id=?", [$id])
                    : $rolePostU;
                // post_email() retourne NULL si format invalide -> garder l'email actuel
                $email = post_email('email') ?? db_scalar("SELECT email FROM utilisateurs WHERE id=?", [$id]);
                db_exec(
                    "UPDATE utilisateurs SET nom=?,prenom=?,email=?,role=?,specialite=?,telephone=?,extension=?,statut=?,planning=?,avatar_initiales=? WHERE id=?",
                    [$v->get('nom'),$v->get('prenom'),$email,$role,
                     post_str('specialite'),post_str('telephone'),post_str('extension'),
                     $v->get('statut'),post_str('planning'),$init,$id]
                );
                invalidate_roles_cache();
                logActivity('Utilisateur modifié ID:'.$id, 'blue', 'utilisateur', $id);
                header('Location: '.APP_URL.'/utilisateurs.php?saved=1'); exit;
            }
        }
    }
}

//  CHANGER MOT DE PASSE (admin force ou self)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'change_password') {
    csrf_verify();
    $id      = post_int('user_id');
    $newpass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $isSelf  = $id === (int)($_SESSION['user_id'] ?? 0);
    // Si self, vérifier l'ancien mot de passe
    if ($isSelf) {
        $old = $_POST['old_password'] ?? '';
        $hash = db_scalar("SELECT mot_de_passe FROM utilisateurs WHERE id=?", [$id]);
        if (!password_verify($old, $hash)) { $msg = 'Ancien mot de passe incorrect.'; $msgType = 'red'; }
    }
    if (!$msg && mb_strlen($newpass) < 8) { $msg = 'Minimum 8 caractères.'; $msgType = 'red'; }
    if (!$msg && $newpass !== $confirm)    { $msg = 'Mots de passe différents.'; $msgType = 'red'; }
    if (!$msg) {
        assert_owns('utilisateurs', $id);
        db_exec("UPDATE utilisateurs SET mot_de_passe=? WHERE id=?", [password_hash($newpass, PASSWORD_BCRYPT, ['cost'=>12]), $id]);
        logActivity('Mot de passe changé ID:'.$id, 'blue', 'utilisateur', $id);
        $msg = 'Mot de passe mis à jour avec succès.'; $msgType = 'green';
    }
}

//  SUPPRIMER DÉFINITIVEMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'delete_user') {
    csrf_verify();
    $id = post_int('user_id');
    if ($id === (int)($_SESSION['user_id'] ?? 0)) {
        $msg = 'Impossible de supprimer votre propre compte.'; $msgType = 'red';
    } else {
        $nom = db_scalar("SELECT CONCAT(prenom,' ',nom) FROM utilisateurs WHERE id=?", [$id]);
        // Nettoyer les relations avant suppression
        db_exec("UPDATE activite_log    SET utilisateur_id=NULL WHERE utilisateur_id=?", [$id]);
        db_exec("UPDATE rendez_vous     SET medecin_id=NULL     WHERE medecin_id=?",     [$id]);
        db_exec("UPDATE hospitalisations SET medecin_id=NULL    WHERE medecin_id=?",     [$id]);
        db_exec("UPDATE analyses        SET prescripteur_id=NULL WHERE prescripteur_id=?",[$id]);
        db_exec("UPDATE caisse_ventes   SET caissier_id=NULL   WHERE caissier_id=?",    [$id]);
        db_exec("UPDATE ordonnances     SET medecin_id=NULL    WHERE medecin_id=?",      [$id]);
        db_exec("DELETE FROM utilisateurs WHERE id=?", [$id]);
        invalidate_roles_cache();
        logActivity("Utilisateur supprimé définitivement: $nom (ID:$id)", 'red', 'utilisateur');
        $msg = "Utilisateur supprimé définitivement."; $msgType = 'green';
    }
}

//  DÉSACTIVER / ACTIVER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'toggle_statut') {
    csrf_verify();
    $id = post_int('user_id');
    if ($id === (int)($_SESSION['user_id'] ?? 0)) { $msg = 'Impossible de modifier votre propre statut.'; $msgType = 'red'; }
    else {
        $cur = db_scalar("SELECT statut FROM utilisateurs WHERE id=?", [$id]);
        $new = ($cur === 'actif') ? 'inactif' : 'actif';
        db_exec("UPDATE utilisateurs SET statut=? WHERE id=?", [$new, $id]);
        logActivity("Utilisateur ID:$id -> $new", $new === 'actif' ? 'green' : 'yellow', 'utilisateur', $id);
        $msg = 'Statut mis à jour.'; $msgType = 'green';
    }
}

//  EXPORT CSV
if (get_str('export') === 'csv') {
    if (ob_get_level() > 0) ob_end_clean();
    $all = db_select("SELECT prenom,nom,email,role,specialite,statut,planning,derniere_connexion,created_at FROM utilisateurs ORDER BY role,nom");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="personnel_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output','w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Prénom','Nom','Email','Rôle','Spécialité','Statut','Planning','Dernière connexion','Créé le'], ';');
    foreach ($all as $r) {
        fputcsv($out, [$r['prenom'],$r['nom'],$r['email'],role_label($r['role']),$r['specialite']??'',$r['statut']??'',$r['planning']??'',$r['derniere_connexion']?fmt_date($r['derniere_connexion'],true):'Jamais',$r['created_at']?fmt_date($r['created_at']):''], ';');
    }
    fclose($out); exit;
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('utilisateurs');

//  DONNÉES
$filterRole = '';
$__allRoles = array_keys($rolesMap); // Utilise $rolesMap déjà chargé depuis BDD ci-dessus
if (in_array(get_str('role_filter'), array_merge([''], $__allRoles), true)) {
    $filterRole = get_str('role_filter');
}
$search     = get_str('search');
$whereParts = []; $wParams = [];
if ($filterRole) { $whereParts[] = "u.role=?"; $wParams[] = $filterRole; }
if ($search)     { $whereParts[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)"; $l="%$search%"; $wParams=array_merge($wParams,[$l,$l,$l]); }
$where = $whereParts ? 'WHERE '.implode(' AND ', $whereParts) : '';

$users = db_select(
    "SELECT u.*,
     (SELECT COUNT(*) FROM rendez_vous r WHERE r.medecin_id=u.id AND DATE(r.date_heure)=CURDATE()) AS rdv_today
     FROM utilisateurs u $where ORDER BY u.role,u.nom ASC", $wParams
);
$total    = (int)db_scalar("SELECT COUNT(*) FROM utilisateurs");
$actifs   = (int)db_scalar("SELECT COUNT(*) FROM utilisateurs WHERE statut='actif'");


$editUser = null;
if (get_int('edit_id') > 0) $editUser = db_row("SELECT * FROM utilisateurs WHERE id=?", [get_int('edit_id')]);
$selfId = (int)($_SESSION['user_id'] ?? 0);
?>

<?php if (isset($_GET['saved'])): ?><div class="alert alert-green alert-auto"> Enregistré avec succès.</div><?php endif; ?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType==='green'?'green':($msgType==='yellow'?'yellow':'red') ?> alert-auto"><?= $msgType==='green'?'':($msgType==='yellow'?'':'') ?> <?= h($msg) ?></div><?php endif; ?>

<div class="page-header-row">
  <div><h2>Gestion des utilisateurs</h2><p><?= $total ?> comptes  <?= $actifs ?> actifs</p></div>
  <div style="display:flex;gap:8px">
    <a href="utilisateurs.php?export=csv" class="btn btn-ghost"> ⬇ Exporter</a>
    <button class="btn btn-blue" onclick="document.getElementById('modal-create').style.display='flex'">+ Nouveau compte</button>
  </div>
</div>

<!-- Filtres -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <form method="GET" style="display:flex;gap:6px;flex:1;flex-wrap:wrap">
    <input type="text" name="search" value="<?= h($search) ?>" placeholder=" Nom, email..."
      style="flex:1;min-width:180px;padding:8px 12px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text);font-size:13px;outline:none">
    <select name="role_filter" onchange="this.form.submit()"
      style="padding:8px 12px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text);font-family:inherit;font-size:13px;outline:none">
      <option value="">Tous les rôles</option>
      <?php foreach ($rolesMap as $rk => $ri): ?>
      <option value="<?= h($rk) ?>" <?= $filterRole===$rk?'selected':'' ?>><?= h($ri['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-blue btn-sm">Filtrer</button>
    <?php if ($search||$filterRole): ?><a href="utilisateurs.php" class="btn btn-ghost btn-sm">✕</a><?php endif; ?>
  </form>
</div>

<!-- Tableau -->
<div class="card">
  <table>
    <thead>
      <tr><th>Utilisateur</th><th>Rôle</th><th>Spécialité</th><th>Statut</th><th>RDV aujourd'hui</th><th>Dernière connexion</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u):
      $rColor = $rolesMap[$u['role']]['couleur'] ?? '#3b82f6';
      $rLabel = $rolesMap[$u['role']]['label']   ?? ucfirst($u['role']);
      $init   = h($u['avatar_initiales'] ?? strtoupper(mb_substr($u['prenom'],0,1).mb_substr($u['nom'],0,1)));
      $isSelf = $u['id'] === $selfId;
      $sBadge = ['actif'=>'badge-green','inactif'=>'badge-red','conge'=>'badge-yellow'];
      $sLabel = ['actif'=>'Actif','inactif'=>'Inactif','conge'=>'Congé'];
    ?>
    <tr>
      <td>
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:34px;height:34px;border-radius:9px;background:<?= h($rColor) ?>22;color:<?= h($rColor) ?>;border:1px solid <?= h($rColor) ?>44;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0"><?= $init ?></div>
          <div>
            <div style="font-weight:600;font-size:13px"><?= h($u['prenom'].' '.$u['nom']) ?> <?= $isSelf?'<span style="font-size:10px;color:var(--text3)">(vous)</span>':'' ?></div>
            <div class="text-xs text3"><?= h($u['email']) ?></div>
          </div>
        </div>
      </td>
      <td>
        <span style="background:<?= h($rColor) ?>22;color:<?= h($rColor) ?>;border:1px solid <?= h($rColor) ?>44;padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600"><?= h($rLabel) ?></span>
      </td>
      <td style="font-size:12px;color:var(--text2)"><?= h($u['specialite']??'') ?></td>
      <td><span class="badge <?= $sBadge[$u['statut']]??'badge-gray' ?>"><?= $sLabel[$u['statut']]??$u['statut'] ?></span></td>
      <td style="text-align:center"><?= (int)$u['rdv_today'] ?></td>
      <td style="font-size:11px;color:var(--text3)"><?= $u['derniere_connexion']?fmt_date($u['derniere_connexion'],true):'Jamais' ?></td>
      <td style="display:flex;gap:4px;flex-wrap:wrap">
        <a href="?edit_id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-ghost" title="Modifier">✏️</a>
        <button class="btn btn-sm btn-ghost" title="Changer mot de passe"
          onclick="openPwdModal(<?= (int)$u['id'] ?>, '<?= h(addslashes($u['prenom'].' '.$u['nom'])) ?>', <?= $isSelf?'true':'false' ?>)">🔑</button>
        <?php if (!$isSelf): ?>
        <form method="POST" style="margin:0" onsubmit="return confirm('<?= $u['statut']==='actif'?'Désactiver':'Activer' ?> ce compte ?')">
          <input type="hidden" name="action" value="toggle_statut">
          <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-sm btn-ghost" title="<?= $u['statut']==='actif'?'Désactiver':'Activer' ?>"><?= $u['statut']==='actif'?'🔴':'🟢' ?></button>
        </form>
        <button class="btn btn-sm btn-red" title="Supprimer définitivement"
          onclick="confirmDelete(<?= (int)$u['id'] ?>, '<?= h(addslashes($u['prenom'].' '.$u['nom'])) ?>')">🗑️</button>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($users)): ?>
    <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text3)">Aucun utilisateur trouvé</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<!--  MODAL MODIFIER UTILISATEUR
<?php if ($editUser): ?>
<div id="modal-edit" style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:600px;margin:auto;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>✏️ Modifier — <?= h($editUser['prenom'].' '.$editUser['nom']) ?></h3>
      <a href="utilisateurs.php" style="color:var(--text2);text-decoration:none;font-size:18px"></a>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="update_user">
      <input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group"><label>Prénom *</label><input type="text" name="prenom" value="<?= h($editUser['prenom']) ?>" required maxlength="100"></div>
        <div class="form-group"><label>Nom *</label><input type="text" name="nom" value="<?= h($editUser['nom']) ?>" required maxlength="100"></div>
        <div class="form-group"><label>Email *</label><input type="email" name="email" value="<?= h($editUser['email']) ?>" required maxlength="150"></div>
        <div class="form-group"><label>Rôle<?= $editUser['id']===$selfId?' (non modifiable)':' *' ?></label>
          <?php if ($editUser['id'] === $selfId): ?>
            <input type="hidden" name="role" value="<?= h($editUser['role']) ?>">
            <div style="padding:9px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:7px;font-size:13px;color:var(--text2)"><?= h(role_label($editUser['role'])) ?></div>
          <?php else: ?>
            <select name="role" required style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
              <?php foreach ($rolesMap as $rk => $ri): ?>
              <option value="<?= h($rk) ?>" <?= $editUser['role']===$rk?'selected':'' ?>><?= h($ri['label']) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <div class="form-group"><label>Statut</label>
          <select name="statut" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value="actif"   <?= $editUser['statut']==='actif'?'selected':'' ?>>✅ Actif</option>
            <option value="inactif" <?= $editUser['statut']==='inactif'?'selected':'' ?>>🚫 Inactif</option>
            <option value="conge"   <?= $editUser['statut']==='conge'?'selected':'' ?>> 🏖️ En congé</option>
          </select>
        </div>
        <div class="form-group"><label>Spécialité</label><input type="text" name="specialite" value="<?= h($editUser['specialite']??'') ?>" maxlength="100"></div>
        <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone" value="<?= h($editUser['telephone']??'') ?>" maxlength="20"></div>
        <div class="form-group"><label>Extension</label><input type="text" name="extension" value="<?= h($editUser['extension']??'') ?>" maxlength="10"></div>
        <div class="form-group form-full"><label>Planning</label><input type="text" name="planning" value="<?= h($editUser['planning']??'') ?>" maxlength="100" placeholder="ex: Lun-Ven 08h-17h"></div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <a href="utilisateurs.php" class="btn btn-ghost">Annuler</a>
        <button type="submit" class="btn btn-blue">💾 Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!--  MODAL CRÉER UTILISATEUR
<div id="modal-create" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:600px;margin:auto;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>+ Nouveau compte</h3>
      <div onclick="document.getElementById('modal-create').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)"></div>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="create_user"><?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group"><label>Prénom *</label><input type="text" name="prenom" required maxlength="100" autofocus></div>
        <div class="form-group"><label>Nom *</label><input type="text" name="nom" required maxlength="100"></div>
        <div class="form-group"><label>Email *</label><input type="email" name="email" required maxlength="150"></div>
        <div class="form-group"><label>Rôle *</label>
          <select name="role" required style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <?php foreach ($rolesMap as $rk => $ri): ?>
            <option value="<?= h($rk) ?>"><?= h($ri['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Mot de passe * <small style="color:var(--text3)">(min 8 car.)</small></label>
          <input type="password" name="password" required minlength="8" autocomplete="new-password" id="pw-new"></div>
        <div class="form-group"><label>Confirmer</label>
          <input type="password" id="pw-confirm" required minlength="8" autocomplete="new-password" oninput="checkPw()"></div>
        <div class="form-group"><label>Statut</label>
          <select name="statut" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value="actif">✅ Actif</option><option value="inactif">🚫 Inactif</option>
          </select>
        </div>
        <div class="form-group"><label>Spécialité</label><input type="text" name="specialite" maxlength="100" placeholder="ex: Cardiologie"></div>
        <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone" maxlength="20"></div>
        <div class="form-group"><label>Extension</label><input type="text" name="extension" maxlength="10"></div>
        <div class="form-group form-full"><label>Planning</label><input type="text" name="planning" maxlength="100" placeholder="ex: Lun-Ven 08h-17h"></div>
      </div>
      <div id="pw-match-msg" style="font-size:12px;color:var(--red);margin-top:4px;display:none">Mots de passe différents</div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-create').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">Créer le compte</button>
      </div>
    </form>
  </div>
</div>

<!--  MODAL CHANGER MOT DE PASSE  -->
<div id="modal-pwd" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:210;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:420px;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3> Changer le mot de passe</h3>
      <div onclick="document.getElementById('modal-pwd').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)"></div>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="change_password">
      <input type="hidden" name="user_id" id="pwd-user-id">
      <?= csrf_field() ?>
      <div id="pwd-user-name" style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--accent2)"></div>
      <div id="old-pwd-group" class="form-group" style="display:none;margin-bottom:14px">
        <label>Mot de passe actuel *</label>
        <input type="password" name="old_password" id="inp-old-pwd" autocomplete="current-password">
      </div>
      <div class="form-group" style="margin-bottom:14px">
        <label>Nouveau mot de passe * <small style="color:var(--text3)">(min 8 car.)</small></label>
        <input type="password" name="new_password" id="inp-new-pwd" required minlength="8" autocomplete="new-password">
      </div>
      <div class="form-group" style="margin-bottom:20px">
        <label>Confirmer *</label>
        <input type="password" name="confirm_password" id="inp-confirm-pwd" required autocomplete="new-password">
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-pwd').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">Changer le mot de passe</button>
      </div>
    </form>
  </div>
</div>

<!--  FORMULAIRE SUPPRESSION DÉFINITIVE (hors boucle)
<form method="POST" id="form-delete-user" style="display:none">
  <input type="hidden" name="action" value="delete_user">
  <input type="hidden" name="user_id" id="delete-user-id">
  <?= csrf_field() ?>
</form>

<script>
function checkPw() {
  const a = document.getElementById('pw-new').value;
  const b = document.getElementById('pw-confirm').value;
  document.getElementById('pw-match-msg').style.display = (b && a !== b) ? 'block' : 'none';
}

function openPwdModal(id, name, isSelf) {
  document.getElementById('pwd-user-id').value = id;
  document.getElementById('pwd-user-name').textContent = ' ' + name;
  document.getElementById('old-pwd-group').style.display = isSelf ? 'block' : 'none';
  if (isSelf) document.getElementById('inp-old-pwd').required = true;
  document.getElementById('modal-pwd').style.display = 'flex';
  setTimeout(() => (isSelf ? document.getElementById('inp-old-pwd') : document.getElementById('inp-new-pwd')).focus(), 100);
}

function confirmDelete(id, name) {
  if (!confirm('⚠️ Supprimer DÉFINITIVEMENT "' + name + '" ?\n\nCette action est irréversible. Ses consultations et hospitalisations seront conservées mais dissociées.')) return;
  document.getElementById('delete-user-id').value = id;
  document.getElementById('form-delete-user').submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php';
