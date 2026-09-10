<?php
$currentPage = 'medecins';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

//  CRER membre du personnel
if (can('medecins.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'create_staff') {
    csrf_verify();
    $v = (new Validator())
        ->required('prenom', 'Prénom')->required('nom', 'Nom')
        ->username('username', 'Identifiant')
        ->email('email', 'Email')
        ->whitelist('role', get_all_roles(), 'Rôle')
        ->whitelist('statut', ['actif','inactif','conge'], 'Statut');
    if (!$v->passes()) { $flash = ['red', $v->first_error()]; }
    else {
        $exists = db_scalar("SELECT COUNT(*) FROM utilisateurs WHERE username=?", [$v->get('username')]);
        if ($exists) { $flash = ['red', 'Cet identifiant est déjà utilisé.']; }
        else {
            $hash = password_hash('medicore2026', PASSWORD_BCRYPT, ['cost'=>12]);
            $init = strtoupper(mb_substr($v->get('prenom'),0,1).mb_substr($v->get('nom'),0,1));
            db_exec("INSERT INTO utilisateurs (nom,prenom,username,email,mot_de_passe,role,specialite,telephone,extension,statut,planning,avatar_initiales) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                [$v->get('nom'),$v->get('prenom'),$v->get('username'),$v->get('email'),$hash,$v->get('role'),
                 post_str('specialite'),post_str('telephone'),post_str('extension'),
                 $v->get('statut'),post_str('planning'),$init]);
            logActivity('Nouveau personnel: '.$v->get('prenom').' '.$v->get('nom'), 'green', 'utilisateur');
            $flash = ['green', 'Membre ajouté. Mot de passe par défaut : medicore2026'];
        }
    }
}

//  MODIFIER MEMBRE DU PERSONNEL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'update_medecin' && can('medecins.create')) {
    csrf_verify();
    $id = post_int('medecin_id');
    $v  = (new Validator())->required('prenom','Prénom')->required('nom','Nom')->username('username','Identifiant')->email('email','Email');
    if (!$v->passes()) { $flash = ['red', $v->first_error()]; }
    else {
        $roleM  = post_str('role');
        $roleOk = (bool)db_scalar("SELECT COUNT(*) FROM roles_config WHERE role=? AND actif=1", [$roleM]);
        if (!$roleOk && !empty(get_all_roles())) { $flash = ['red','Role invalide.']; }
        else {
            $roleM = $roleOk ? $roleM : db_scalar("SELECT role FROM utilisateurs WHERE id=?", [$id]);
            db_exec(
                "UPDATE utilisateurs SET prenom=?,nom=?,username=?,email=?,role=?,specialite=?,telephone=?,planning=?,statut=? WHERE id=?",
                [$v->get('prenom'),$v->get('nom'),$v->get('username'),post_email('email')??'',$roleM,
                 post_str('specialite'),post_str('telephone'),post_str('planning'),
                 in_whitelist(post_str('statut'),['actif','inactif','conge'],'actif'),$id]
            );
            invalidate_roles_cache();
            logActivity('Personnel modifié ID:'.$id, 'blue', 'utilisateur', $id);
            header('Location: '.APP_URL.'/medecins.php?saved=1'); exit;
        }
    }
}

//  CHANGER STATUT
if (get_str('action') === 'toggle' && get_int('id') > 0) {
    $id = get_int('id');
    $u  = assert_owns('utilisateurs', $id);
    $ns = $u['statut'] === 'actif' ? 'inactif' : 'actif';
    db_exec("UPDATE utilisateurs SET statut=? WHERE id=?", [$ns, $id]);
    header('Location: '.APP_URL.'/medecins.php?ok=1'); exit;
}

//  DONNES
require_once __DIR__ . '/../includes/layout.php';
$editMedecin = null;
if (get_int('edit_id') > 0 && can('medecins.create')) {
    $editMedecin = db_row("SELECT * FROM utilisateurs WHERE id=?", [get_int('edit_id')]);
}

requirePageAccess('medecins');

$filtre = in_whitelist(get_str('filtre'), array_merge([''], get_all_roles()), '');
$search = get_str('search');
$params = [];
$where  = 'WHERE 1=1';
if ($filtre) { $where .= ' AND u.role=?'; $params[] = $filtre; }
if ($search) { $where .= ' AND (CONCAT(u.prenom," ",u.nom) LIKE ? OR u.specialite LIKE ? OR u.username LIKE ? OR u.email LIKE ?)'; $like="%$search%"; $params=array_merge($params,[$like,$like,$like,$like]); }

$staff = db_select("SELECT u.*,
    (SELECT COUNT(*) FROM rendez_vous r WHERE r.medecin_id=u.id AND DATE(r.date_heure)=CURDATE()) AS rdv_today,
    (SELECT COUNT(*) FROM hospitalisations h WHERE h.medecin_id=u.id AND h.statut='en_cours') AS patients_actifs
    FROM utilisateurs u $where ORDER BY u.role,u.nom", $params);

$totaux = [
    'total'      => (int)db_scalar("SELECT COUNT(*) FROM utilisateurs"),
    'actifs'     => (int)db_scalar("SELECT COUNT(*) FROM utilisateurs WHERE statut='actif'"),
    'medecins'   => (int)db_scalar("SELECT COUNT(*) FROM utilisateurs WHERE role='medecin'"),
    'infirmiers' => (int)db_scalar("SELECT COUNT(*) FROM utilisateurs WHERE role='infirmier'"),
];

$roleBadge  = ['admin'=>['badge-red','Administrateur'],'medecin'=>['badge-blue','Médecin'],
               'infirmier'=>['badge-green','Infirmier(ère)'],'pharmacien'=>['badge-purple','Pharmacien'],
               'comptable'=>['badge-yellow','Comptable']];
$statutBadge = ['actif'=>['badge-green','✅ Actif'],'inactif'=>['badge-red','🚫 Inactif'],'conge'=>['badge-yellow','🏖️ Congéé']];
$plannings   = ['Matin (07h-15h)','Journe (08h-18h)','Après-midi (15h-23h)','Nuit (23h-07h)','Variable'];
?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-green alert-auto"> Modifications enregistrées avec succès.</div>
<?php endif; ?>
<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= $flash[0]==='green'?'':'' ?> <?= h($flash[1]) ?></div>
<?php endif; ?>

<div class="page-header-row">
  <div><h2> Personnel médical</h2><p><?= $totaux['total'] ?> membres  <?= $totaux['actifs'] ?> actifs  <?= $totaux['medecins'] ?> médecins  <?= $totaux['infirmiers'] ?> infirmiers</p></div>
  <?php if (can('medecins.create')): ?><button class="btn btn-blue" onclick="document.getElementById('modal-staff').style.display='flex'">+ Ajouter membre</button><?php endif; ?>
</div>

<div class="stats-grid mb-24">
  <div class="stat-card blue"><div class="stat-icon blue">👥</div><div class="stat-value"><?= $totaux['total'] ?></div><div class="stat-label">Total personnel</div></div>
  <div class="stat-card green"><div class="stat-icon green">🩺</div><div class="stat-value"><?= $totaux['medecins'] ?></div><div class="stat-label">Médecins</div></div>
  <div class="stat-card cyan"><div class="stat-icon cyan">💉</div><div class="stat-value"><?= $totaux['infirmiers'] ?></div><div class="stat-label">Infirmiers</div></div>
  <div class="stat-card red"><div class="stat-icon red">⛔</div><div class="stat-value"><?= $totaux['total']-$totaux['actifs'] ?></div><div class="stat-label">Inactifs / Congé</div></div>
</div>

<!-- Filtres -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <form method="GET" style="display:flex;gap:8px;flex:1">
    <input type="text" name="search" value="<?= h($search) ?>" placeholder=" Nom, identifiant, spécialité..."
      style="flex:1;padding:9px 14px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text);font-family:inherit;font-size:13px;outline:none">
    <?php
  $filterOptions = ['' => 'Tous roles'];
  foreach (get_roles_map() as $rk => $ri) $filterOptions[$rk] = $ri['label'];
  foreach ($filterOptions as $val => $lbl):
  ?>
    <label style="display:flex;align-items:center;gap:4px;padding:8px 12px;background:var(--surface);border:1px solid <?= $filtre===$val?'var(--accent)':'var(--border)' ?>;border-radius:8px;cursor:pointer;font-size:12px;color:<?= $filtre===$val?'var(--accent2)':'var(--text2)' ?>">
      <input type="radio" name="filtre" value="<?= $val ?>" <?= $filtre===$val?'checked':'' ?> onchange="this.form.submit()" style="display:none"><?= $lbl ?>
    </label>
    <?php endforeach; ?>
    <?php if ($search): ?><a href="medecins.php" class="btn btn-ghost btn-sm">✕</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-header"><h3>Personnel médical</h3><span style="font-size:12px;color:var(--text2)"><?= count($staff) ?> résultats</span></div>
  <table>
    <thead><tr><th>Membre</th><th>Rôle</th><th>Spécialité</th><th>Extension</th><th>Planning</th><th>Patients actifs</th><th>RDV aujourd'hui</th><th>Statut</th><th>Dernière conn.</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($staff as $u):
      [$rBadge,$rLabel] = $roleBadge[$u['role']] ?? ['badge-gray',$u['role']];
      [$sBadge,$sLabel] = $statutBadge[$u['statut']] ?? ['badge-gray',$u['statut']];
      $init = h($u['avatar_initiales'] ?? strtoupper(mb_substr($u['prenom'],0,1).mb_substr($u['nom'],0,1)));
    ?>
    <tr <?= $u['statut']==='inactif'?'style="opacity:.5"':'' ?>>
      <td>
        <div style="display:flex;align-items:center;gap:10px">
          <div class="user-avatar" style="width:36px;height:36px;font-size:12px"><?= $init ?></div>
          <div><strong><?= h($u['prenom'].' '.$u['nom']) ?></strong><br><span class="text-xs text3">@<?= h($u['username']) ?></span></div>
        </div>
      </td>
      <td><span class="badge <?= $rBadge ?>"><?= $rLabel ?></span></td>
      <td><?= h($u['specialite']??'') ?></td>
      <td style="font-size:12px"> Ext. <?= h($u['extension']??'') ?></td>
      <td style="font-size:11px;color:var(--text2)"><?= h($u['planning']??'') ?></td>
      <td style="text-align:center"><strong><?= (int)$u['patients_actifs'] ?></strong></td>
      <td style="text-align:center"><?= (int)$u['rdv_today'] ?></td>
      <td><span class="badge <?= $sBadge ?>"><?= $sLabel ?></span></td>
      <td style="font-size:11px;color:var(--text3)"><?= $u['derniere_connexion']?fmt_date($u['derniere_connexion'],true):'Jamais' ?></td>
      <td>
        <a href="medecins.php?action=toggle&id=<?= (int)$u['id'] ?>"
           class="btn btn-sm <?= $u['statut']==='actif'?'btn-ghost':'btn-green' ?>"
           onclick="return confirm('<?= $u['statut']==='actif'?'Désactiver':'Activer' ?> ce compte ?')">
          <?= $u['statut']==='actif'?'':'' ?>
        </a>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($staff)): ?><tr><td colspan="10" style="text-align:center;padding:32px;color:var(--text3)">Aucun rsultat</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- MODAL AJOUT -->
<div id="modal-staff" class="modal-overlay" role="dialog" aria-modal="true" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(580px,95vw);max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:var(--surface)">
      <h3> Ajouter un membre du personnel</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('modal-staff').style.display='none'" aria-label="Fermer"></button>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="create_staff"><?= csrf_field() ?>
      <div style="background:rgba(var(--accent-rgb),.08);border:1px solid rgba(var(--accent-rgb),.2);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:12px;color:var(--text2)">
         Mot de passe par défaut : <strong style="color:var(--accent2)">medicore2026</strong> — à changer après la première connexion.
      </div>
      <div class="form-grid">
        <div class="form-group"><label for="med-new-prenom">Prénom *</label><input type="text" name="prenom" id="med-new-prenom" required maxlength="100"></div>
        <div class="form-group"><label for="med-new-nom">Nom *</label><input type="text" name="nom" id="med-new-nom" required maxlength="100"></div>
        <div class="form-group"><label for="med-new-username">Identifiant *</label><input type="text" name="username" id="med-new-username" required maxlength="50" pattern="[a-z0-9_\.]{3,50}" placeholder="ex: j.durand"></div>
        <div class="form-group"><label for="med-new-email">Email</label><input type="email" name="email" id="med-new-email" maxlength="150"></div>
        <div class="form-group"><label for="med-new-role">Rôle *</label>
          <select name="role" id="med-new-role" required>
            <?php
            try {
                $__mRows = getDB()->query("SELECT role,label FROM roles_config WHERE actif=1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
            } catch(Exception $__e) { _log_error('MEDECINS', 'Echec lecture roles_config (create)', __FILE__, __LINE__, $__e); $__mRows = []; }
            if (empty($__mRows)) $__mRows = [
                ['role'=>'medecin','label'=>'Médecin'],
                ['role'=>'infirmier','label'=>'Infirmier(ère)'],
                ['role'=>'pharmacien','label'=>'Pharmacien'],
                ['role'=>'comptable','label'=>'Comptable'],
                ['role'=>'admin','label'=>'Administrateur'],
            ];
            foreach ($__mRows as $__mr):
            ?>
            <option value="<?= h($__mr['role']) ?>"><?= h($__mr['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label for="med-new-specialite">Spécialité</label><input type="text" name="specialite" id="med-new-specialite" maxlength="100"></div>
        <div class="form-group"><label for="med-new-telephone">Téléphone</label><input type="tel" name="telephone" id="med-new-telephone" maxlength="20"></div>
        <div class="form-group"><label for="med-new-extension">Extension interne</label><input type="text" name="extension" id="med-new-extension" maxlength="10" placeholder="ex: 2201"></div>
        <div class="form-group"><label for="med-new-planning">Planning</label>
          <select name="planning" id="med-new-planning">
            <?php foreach ($plannings as $p): ?><option><?= h($p) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label for="med-new-statut">Statut</label>
          <select name="statut" id="med-new-statut"><option value="actif"> Actif</option><option value="inactif">🚫 Inactif</option></select>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-staff').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue"> Ajouter</button>
      </div>
    </form>
  </div>
</div>


<!-- MODAL MODIFIER PERSONNEL -->
<?php if ($editMedecin && can('medecins.create')): ?>
<div id="modal-edit-med" class="modal-overlay" role="dialog" aria-modal="true" style="display:flex;align-items:flex-start;padding:20px;overflow-y:auto">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(580px,95vw);margin:auto;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>✏️ Modifier — <?= h($editMedecin['prenom'].' '.$editMedecin['nom']) ?></h3>
      <a href="medecins.php" style="color:var(--text2);text-decoration:none;font-size:18px"></a>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="update_medecin">
      <input type="hidden" name="medecin_id" value="<?= (int)$editMedecin['id'] ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group"><label>Prénom *</label><input type="text" name="prenom" value="<?= h($editMedecin['prenom']) ?>" required maxlength="100"></div>
        <div class="form-group"><label>Nom *</label><input type="text" name="nom" value="<?= h($editMedecin['nom']) ?>" required maxlength="100"></div>
        <div class="form-group"><label>Identifiant *</label><input type="text" name="username" value="<?= h($editMedecin['username']) ?>" required maxlength="50" pattern="[a-z0-9_\.]{3,50}"></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= h($editMedecin['email']) ?>" maxlength="150"></div>
        <div class="form-group"><label>Rôle</label>
          <select name="role" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <?php
            try {
                $__eRows = getDB()->query("SELECT role,label FROM roles_config WHERE actif=1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
            } catch(Exception $__e) { _log_error('MEDECINS', 'Echec lecture roles_config (edit)', __FILE__, __LINE__, $__e); $__eRows = []; }
            if (empty($__eRows)) $__eRows = [
                ['role'=>'admin','label'=>'Administrateur'],
                ['role'=>'medecin','label'=>'Médecin'],
                ['role'=>'infirmier','label'=>'Infirmier(ère)'],
                ['role'=>'pharmacien','label'=>'Pharmacien'],
                ['role'=>'comptable','label'=>'Comptable'],
            ];
            foreach ($__eRows as $__er):
            ?>
            <option value="<?= h($__er['role']) ?>" <?= $editMedecin['role']===$__er['role']?'selected':'' ?>><?= h($__er['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Spécialité</label><input type="text" name="specialite" value="<?= h($editMedecin['specialite']??'') ?>" maxlength="100"></div>
        <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone" value="<?= h($editMedecin['telephone']??'') ?>" maxlength="20"></div>
        <div class="form-group"><label>Planning</label><input type="text" name="planning" value="<?= h($editMedecin['planning']??'') ?>" maxlength="100" placeholder="Lun-Ven 08h-17h"></div>
        <div class="form-group"><label>Statut</label>
          <select name="statut" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value="actif"   <?= $editMedecin['statut']==='actif'?'selected':'' ?>> Actif</option>
            <option value="inactif" <?= $editMedecin['statut']==='inactif'?'selected':'' ?>>🚫 Inactif</option>
            <option value="conge"   <?= $editMedecin['statut']==='conge'?'selected':'' ?>> Congé</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <a href="medecins.php" class="btn btn-ghost">Annuler</a>
        <button type="submit" class="btn btn-blue">💾 Enregistréer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php';
