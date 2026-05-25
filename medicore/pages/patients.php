<?php
$currentPage = 'patients';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$msg  = '';
$type = '';

//  CRÉER patient
if (can('patients.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'create_patient') {
    csrf_verify();

    $v = (new Validator())
        ->required('prenom', 'Prénom')
        ->required('nom', 'Nom')
        ->date('date_naissance', 'Date de naissance')
        ->whitelist('sexe', ['M','F','Autre'], 'Sexe')
        ->whitelist('assurance', ['CPAM','Mutuelle','Non assuré','Étranger'], 'Assurance')
        ->whitelist('groupe_sanguin', ['A+','A-','B+','B-','AB+','AB-','O+','O-',''], 'Groupe sanguin');

    if (!$v->passes()) {
        $msg = $v->first_error(); $type = 'red';
    } else {
        $num = 'P-' . date('Y') . '-' . str_pad(mt_rand(1,99999), 5, '0', STR_PAD_LEFT);
        db_exec(
            "INSERT INTO patients (numero,nom,prenom,date_naissance,sexe,adresse,telephone,email,num_secu,groupe_sanguin,allergies,antecedents,contact_urgence_nom,contact_urgence_tel,assurance) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$num,$v->get('nom'),$v->get('prenom'),$v->get('date_naissance'),$v->get('sexe'),post_str('adresse'),post_str('telephone'),post_email('email')??'',post_str('num_secu'),$v->get('groupe_sanguin'),post_str('allergies'),post_str('antecedents'),post_str('contact_urgence_nom'),post_str('contact_urgence_tel'),$v->get('assurance')]
        );
        logActivity('Nouveau patient: '.$v->get('prenom').' '.$v->get('nom'), 'green', 'patient');
        $msg = 'Patient créé ('.$num.')'; $type = 'green';
    }
}

//  SUPPRIMER patient
if (get_str('action') === 'delete' && hasRole(['admin'])) {
    $id = get_signed_id('id', 'patient_delete');
    assert_owns('patients', $id);
    db_exec("DELETE FROM patients WHERE id = ?", [$id]);
    logActivity("Patient supprimé ID:$id", 'red', 'patient', $id);
    header('Location: '.APP_URL.'/patients.php?ok=deleted'); exit;
}


//  MODIFIER PATIENT 
if (can('patients.edit') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'update_patient') {
    csrf_verify();
    $id = post_int('patient_id');
    assert_owns('patients', $id);
    $v = (new Validator())
        ->required('prenom', 'Prénom')->required('nom', 'Nom')
        ->date('date_naissance', 'Date de naissance')
        ->whitelist('sexe', ['M','F','Autre'], 'Sexe');
    if (!$v->passes()) { $msg = $v->first_error(); $type = 'red'; }
    else {
        db_exec(
            "UPDATE patients SET nom=?,prenom=?,date_naissance=?,sexe=?,adresse=?,telephone=?,email=?,
             num_secu=?,groupe_sanguin=?,allergies=?,antecedents=?,contact_urgence_nom=?,contact_urgence_tel=?,assurance=?
             WHERE id=?",
            [$v->get('nom'),$v->get('prenom'),$v->get('date_naissance'),$v->get('sexe'),
             post_str('adresse'),post_str('telephone'),post_email('email')??'',post_str('num_secu'),
             post_str('groupe_sanguin'),post_str('allergies'),post_str('antecedents'),
             post_str('contact_urgence_nom'),post_str('contact_urgence_tel'),post_str('assurance'),$id]
        );
        logActivity('Patient modifié: '.$v->get('prenom').' '.$v->get('nom'), 'blue', 'patient', $id);
        header('Location: '.APP_URL.'/patients.php?saved=1'); exit;
    }
}

//  DONNÉES
//  Layout inclus ici  aprs toute logique PHP

//  EXPORT CSV 
if (get_str('export') === 'csv' && can('patients.view')) {
    if (ob_get_level() > 0) ob_end_clean();
    $all = db_select(
        "SELECT p.numero, p.nom, p.prenom, p.date_naissance, p.sexe, p.telephone,
                p.email, p.groupe_sanguin, p.assurance,
                'actif' AS statut,
                (SELECT d.nom FROM hospitalisations h
                 JOIN departements d ON d.id=h.departement_id
                 WHERE h.patient_id=p.id AND h.statut='en_cours' LIMIT 1) AS departement,
                p.date_creation
         FROM patients p
         ORDER BY p.nom ASC"
    );
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="patients_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['N° Dossier','Nom','Prénom','Naissance','Sexe','Téléphone','Email','Groupe sanguin','Assurance','Statut','Département','Créé le'], ';');
    foreach ($all as $row) {
        fputcsv($out, [
            $row['numero'], $row['nom'], $row['prenom'],
            $row['date_naissance'] ? fmt_date($row['date_naissance']) : '',
            $row['sexe'] === 'M' ? 'Homme' : 'Femme',
            $row['telephone'] ?? '', $row['email'] ?? '',
            $row['groupe_sanguin'] ?? '', $row['assurance'] ?? '',
            $row['statut'] ?? '', $row['departement'] ?? '',
            $row['date_creation'] ? fmt_date($row['date_creation']) : '',
        ], ';');
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('patients');
$search      = get_str('search');
$filtreHosp  = in_whitelist(get_str('filtre_hosp'), ['', 'hospitalise', 'ambulatoire'], '');
$like        = '%'.$search.'%';
$conditions  = [];
$params      = [];

if ($search) {
    $conditions[] = "(p.nom LIKE ? OR p.prenom LIKE ? OR p.numero LIKE ? OR p.telephone LIKE ?)";
    $params = array_merge($params, [$like,$like,$like,$like]);
}
if ($filtreHosp === 'hospitalise') {
    $conditions[] = "EXISTS (SELECT 1 FROM hospitalisations h WHERE h.patient_id=p.id AND h.statut='en_cours')";
} elseif ($filtreHosp === 'ambulatoire') {
    $conditions[] = "NOT EXISTS (SELECT 1 FROM hospitalisations h WHERE h.patient_id=p.id AND h.statut='en_cours')";
}
$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$patients = db_select(
    "SELECT p.*,
     (SELECT h.priorite FROM hospitalisations h WHERE h.patient_id=p.id AND h.statut='en_cours' LIMIT 1) AS priorite,
     (SELECT d.nom FROM hospitalisations h JOIN departements d ON d.id=h.departement_id WHERE h.patient_id=p.id AND h.statut='en_cours' LIMIT 1) AS dept_nom,
     (SELECT l.numero FROM hospitalisations h JOIN lits l ON l.id=h.lit_id WHERE h.patient_id=p.id AND h.statut='en_cours' LIMIT 1) AS lit_num
     FROM patients p $whereSql ORDER BY p.date_creation DESC LIMIT 200",
    $params
);
$total = (int)db_scalar("SELECT COUNT(*) FROM patients");
$hosp  = (int)db_scalar("SELECT COUNT(DISTINCT patient_id) FROM hospitalisations WHERE statut='en_cours'");
// Patient a editer (si ?edit_id=...)
$editPatient = null;
if (can('patients.edit') && get_int('edit_id') > 0) {
    $editPatient = db_row("SELECT * FROM patients WHERE id=?", [get_int('edit_id')]);
    if ($editPatient) {
        db_exec("INSERT INTO audit_acces (utilisateur_id, patient_id, type_acces, entite, entite_id, adresse_ip) VALUES (?,?,?,?,?,?)",
            [currentUser()['id'], $editPatient['id'], 'modification', 'fiche_patient', $editPatient['id'], $_SERVER['REMOTE_ADDR']??'']);
    }
}
?>

<?php if (isset($_GET['saved'])): ?><div class="alert alert-green alert-auto"> Enregistré avec succès.</div><?php endif; ?>
<?php if ($msg): ?><div class="alert alert-<?= $type === 'green' ? 'green' : 'red' ?> alert-auto"><?= $type==='green'?'':'' ?> <?= h($msg) ?></div><?php endif; ?>
<?php if (get_str('ok') === 'deleted'): ?><div class="alert alert-green alert-auto"> Patient supprimé.</div><?php endif; ?>

<div class="page-header-row">
  <div><h2> Gestion des patients</h2><p><?= $total ?> patients  <?= $hosp ?> hospitalisés</p></div>
  <div style="display:flex;gap:8px">
    <?php if (can('patients.view')): ?>
    <a href="patients.php?export=csv" class="btn btn-ghost"> ⬇ Exporter CSV</a>
    <?php endif; ?>
    <?php if (can('patients.create')): ?>
    <button class="btn btn-blue" onclick="document.getElementById('modal-patient').style.display='flex'">+ Nouveau patient</button>
    <?php endif; ?>
  </div>
</div>

<form method="GET" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
  <input type="text" name="search" value="<?= h($search) ?>" placeholder=" Rechercher nom, numéro, téléphone..." style="flex:1;min-width:200px;padding:9px 14px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text);font-family:inherit;font-size:13px;outline:none">
  <?php foreach ([''=>'Tous','hospitalise'=>' Hospitaliss','ambulatoire'=>' Ambulatoires'] as $v=>$l): ?>
  <label style="display:flex;align-items:center;padding:8px 13px;background:var(--surface);border:1px solid <?= $filtreHosp===$v?'var(--accent)':'var(--border)' ?>;border-radius:8px;cursor:pointer;font-size:12px;color:<?= $filtreHosp===$v?'var(--accent2)':'var(--text2)' ?>;white-space:nowrap">
    <input type="radio" name="filtre_hosp" value="<?= $v ?>" <?= $filtreHosp===$v?'checked':'' ?> onchange="this.form.submit()" style="display:none"><?= $l ?>
  </label>
  <?php endforeach; ?>
  <button type="submit" class="btn btn-blue">Chercher</button>
  <?php if ($search||$filtreHosp): ?><a href="patients.php" class="btn btn-ghost">✕</a><?php endif; ?>
</form>

<div class="card">
  <div class="card-header"><h3>Patients <?= $search ? ' "'.h($search).'"' : '' ?></h3><span style="font-size:12px;color:var(--text2)"><?= count($patients) ?> résultats</span></div>
  <table>
    <thead><tr><th>Patient</th><th>Âge/Sexe</th><th>Téléphone</th><th>Assurance</th><th>Département</th><th>Statut</th><th>Créé le</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($patients as $p):
        $age   = date_diff(date_create($p['date_naissance']), date_create())->y;
        $init  = strtoupper(mb_substr($p['prenom'],0,1).mb_substr($p['nom'],0,1));
        $badge = match($p['priorite']??'') { 'critique'=>'badge-red','urgent'=>'badge-yellow', default=>'badge-gray' };
        $label = match($p['priorite']??'') { 'critique'=>'Critique','urgent'=>'Urgent', default=>($p['dept_nom']?'Hospitalis':'Ambulatoire') };
        $dossierUrl = secure_url('dossiers.php', (int)$p['id'], 'dossier');
        $deleteUrl  = secure_url('patients.php',  (int)$p['id'], 'patient_delete').'&action=delete';
      ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="patient-avatar" style="width:34px;height:34px;font-size:11px"><?= h($init) ?></div>
            <div><strong><?= h($p['prenom'].' '.$p['nom']) ?></strong><br><span class="text-xs text3"><?= h($p['numero']) ?></span></div>
          </div>
        </td>
        <td><?= (int)$age ?> ans  <?= $p['sexe']==='M'?'H':($p['sexe']==='F'?'F':'A') ?></td>
        <td><?= h($p['telephone']??'') ?></td>
        <td><?= h($p['assurance']??'') ?></td>
        <td><?= h($p['dept_nom']??'') ?><?= $p['lit_num']?'  Lit '.h($p['lit_num']):'' ?></td>
        <td><span class="badge <?= $badge ?>"><?= $label ?></span></td>
        <td><?= fmt_date($p['date_creation']) ?></td>
        <td style="display:flex;gap:4px">
          <?php if (canAccessPage('dossiers')): ?>
          <a href="dossiers.php?patient_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-blue" title="Dossier médical">📋 Dossier</a>
          <?php endif; ?>
          <?php if (can('patients.edit')): ?>
          <a href="patients.php?edit_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-ghost" title="Modifier">✏️</a>
          <?php endif; ?>
          <?php if (can('hospitalisations.create') && !$p['dept_nom']): ?>
          <a href="urgences.php" class="btn btn-sm" style="background:rgba(239,68,68,.15);color:var(--red);border:none;padding:4px 8px;border-radius:6px;font-size:11px;cursor:pointer;text-decoration:none" title="Admettre en urgences">🚨</a>
          <?php endif; ?>
          <?php if (hasRole(['admin'])): ?>
          <a href="<?= h($deleteUrl) ?>" class="btn btn-sm btn-red" onclick="return confirm('Supprimer définitivement ?')">🗑</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($patients)): ?><tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text3)">Aucun résultat</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- MODAL NOUVEAU PATIENT  CSRF protégé -->
<div id="modal-patient" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:600px;max-height:85vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.6)">
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--surface)">
      <h3>+ Nouveau patient</h3>
      <div onclick="document.getElementById('modal-patient').style.display='none'" style="cursor:pointer;color:var(--text2);font-size:18px;width:28px;height:28px;background:var(--surface2);border-radius:6px;display:flex;align-items:center;justify-content:center"></div>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="create_patient">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group"><label>Prénom *</label><input type="text" name="prenom" required maxlength="100"></div>
        <div class="form-group"><label>Nom *</label><input type="text" name="nom" required maxlength="100"></div>
        <div class="form-group"><label>Date de naissance *</label><input type="date" name="date_naissance" required max="<?= date('Y-m-d') ?>"></div>
        <div class="form-group"><label>Sexe *</label><select name="sexe" required><option value="F">Féminin</option><option value="M">Masculin</option><option value="Autre">Autre</option></select></div>
        <div class="form-group form-full"><label>Adresse</label><input type="text" name="adresse" maxlength="255"></div>
        <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone" maxlength="20"></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" maxlength="150"></div>
        <div class="form-group"><label>N° Sécurité sociale</label><input type="text" name="num_secu" maxlength="20"></div>
        <div class="form-group"><label>Groupe sanguin</label>
          <select name="groupe_sanguin"><option value=""> Inconnu </option><option>A+</option><option>A-</option><option>B+</option><option>B-</option><option>AB+</option><option>AB-</option><option>O+</option><option>O-</option></select></div>
        <div class="form-group"><label>Assurance</label><select name="assurance"><option>CPAM</option><option>Mutuelle</option><option>Non assuré</option><option>Étranger</option></select></div>
        <div class="form-group form-full"><label>Allergies</label><input type="text" name="allergies" maxlength="500" placeholder="ex: Pénicilline, Aspirine"></div>
        <div class="form-group form-full"><label>Antécédents médicaux</label><textarea name="antecedents" rows="3" maxlength="2000"></textarea></div>
        <div class="form-group"><label>Contact urgence (nom)</label><input type="text" name="contact_urgence_nom" maxlength="200"></div>
        <div class="form-group"><label>Contact urgence (tél)</label><input type="tel" name="contact_urgence_tel" maxlength="20"></div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-patient').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue"> Créer le dossier</button>
      </div>
    </form>
  </div>
</div>


<!-- MODAL EDITER PATIENT -->
<?php if ($editPatient && can('patients.edit')): ?>
<script>document.addEventListener('DOMContentLoaded',()=>{ document.getElementById('modal-edit-patient').style.display='flex'; });</script>
<?php endif; ?>
<div id="modal-edit-patient" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:680px;box-shadow:0 24px 60px rgba(0,0,0,.7);margin:auto">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:var(--surface);border-radius:16px 16px 0 0">
      <h3>✏️ Modifier le dossier patient</h3>
      <a href="patients.php" style="cursor:pointer;font-size:18px;color:var(--text2);text-decoration:none"></a>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="update_patient">
      <input type="hidden" name="patient_id" value="<?= (int)($editPatient['id'] ?? 0) ?>">
      <?= csrf_field() ?>
      <?php $ep = $editPatient ?? []; ?>
      <div style="background:var(--surface2);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:12px;color:var(--text2)">
        Dossier <strong style="color:var(--accent2)"><?= h($ep['numero'] ?? '') ?></strong>
         Créé le <?= isset($ep['date_creation']) ? fmt_date($ep['date_creation']) : '' ?>
      </div>
      <div class="form-grid">
        <div class="form-group"><label>Prénom *</label><input type="text" name="prenom" value="<?= h($ep['prenom'] ?? '') ?>" required maxlength="100"></div>
        <div class="form-group"><label>Nom *</label><input type="text" name="nom" value="<?= h($ep['nom'] ?? '') ?>" required maxlength="100"></div>
        <div class="form-group"><label>Date de naissance *</label><input type="date" name="date_naissance" value="<?= h($ep['date_naissance'] ?? '') ?>" required></div>
        <div class="form-group"><label>Sexe *</label>
          <select name="sexe" style="padding:9px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value="M" <?= ($ep['sexe']??'')==='M'?'selected':'' ?>>Masculin</option>
            <option value="F" <?= ($ep['sexe']??'')==='F'?'selected':'' ?>>Féminin</option>
            <option value="Autre" <?= ($ep['sexe']??'')==='Autre'?'selected':'' ?>>Autre</option>
          </select>
        </div>
        <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone" value="<?= h($ep['telephone'] ?? '') ?>" maxlength="20"></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= h($ep['email'] ?? '') ?>" maxlength="150"></div>
        <div class="form-group form-full"><label>Adresse</label><input type="text" name="adresse" value="<?= h($ep['adresse'] ?? '') ?>" maxlength="300"></div>
        <div class="form-group"><label>N° Sécurité sociale</label><input type="text" name="num_secu" value="<?= h($ep['num_secu'] ?? '') ?>" maxlength="20"></div>
        <div class="form-group"><label>Groupe sanguin</label>
          <select name="groupe_sanguin" style="padding:9px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <?php foreach ([''=>'','A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?>
            <option value="<?= $g ?>" <?= ($ep['groupe_sanguin']??'')===$g?'selected':'' ?>><?= $g?:'  ' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Assurance</label>
          <select name="assurance" style="padding:9px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <?php foreach (['CPAM','Mutuelle','Non assuré','Étranger'] as $a): ?>
            <option value="<?= h($a) ?>" <?= ($ep['assurance']??'')===$a?'selected':'' ?>><?= h($a) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group form-full"><label>Allergies</label><input type="text" name="allergies" value="<?= h($ep['allergies'] ?? '') ?>" maxlength="500"></div>
        <div class="form-group form-full"><label>Antécédents médicaux</label><textarea name="antecedents" rows="3" maxlength="2000" style="width:100%;padding:9px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;resize:vertical"><?= h($ep['antecedents'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Contact urgence (nom)</label><input type="text" name="contact_urgence_nom" value="<?= h($ep['contact_urgence_nom'] ?? '') ?>" maxlength="200"></div>
        <div class="form-group"><label>Contact urgence (tél)</label><input type="tel" name="contact_urgence_tel" value="<?= h($ep['contact_urgence_tel'] ?? '') ?>" maxlength="20"></div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <a href="patients.php" class="btn btn-ghost">Annuler</a>
        <button type="submit" class="btn btn-blue">💾 Enregistrer les modifications</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';
