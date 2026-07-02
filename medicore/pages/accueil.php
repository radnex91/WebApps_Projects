<?php
$currentPage = 'accueil';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dossiers.php';
require_once __DIR__ . '/../includes/accueil.php';
requireLogin();

$flash = null;

// --- POST : Enregistrer un nouveau patient + check-in ---
if (can('accueil.checkin') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'create_and_checkin') {
    csrf_verify();
    $v = (new Validator())
        ->required('prenom', 'Prénom')
        ->required('nom', 'Nom')
        ->date('date_naissance', 'Date de naissance')
        ->whitelist('sexe', ['M', 'F', 'Autre'], 'Sexe')
        ->whitelist('assurance', ['CPAM', 'Mutuelle', 'Non assuré', 'Étranger'], 'Assurance')
        ->whitelist('groupe_sanguin', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', ''], 'Groupe sanguin');
    if (!$v->passes()) {
        $flash = ['red', $v->first_error()];
    } else {
        $num = 'P-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $pid = db_exec(
            "INSERT INTO patients (numero,nom,prenom,date_naissance,sexe,adresse,telephone,email,num_secu,groupe_sanguin,allergies,antecedents,contact_urgence_nom,contact_urgence_tel,assurance) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$num, $v->get('nom'), $v->get('prenom'), $v->get('date_naissance'), $v->get('sexe'), post_str('adresse'), post_str('telephone'), post_email('email') ?? '', post_str('num_secu'), $v->get('groupe_sanguin'), post_str('allergies'), post_str('antecedents'), post_str('contact_urgence_nom'), post_str('contact_urgence_tel'), $v->get('assurance')]
        );
        $motif = post_str('motif');
        $aid = checkin_patient((int)$pid, (int)currentUser()['id'], $motif);
        logActivity('Accueil : nouveau patient ' . $num . ' (arrivée ' . $aid . ')', 'green', 'patient', (int)$pid);
        $flash = ['green', 'Patient enregistré (' . $num . ') et ajouté à la file d\'attente.'];
    }
}

// --- POST : Check-in d'un patient existant ---
if (can('accueil.checkin') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'checkin') {
    csrf_verify();
    $pid = post_int('patient_id');
    $motif = post_str('motif');
    if ($pid <= 0) {
        $flash = ['red', 'Patient requis.'];
    } else {
        $existante = arrivee_active_du_jour((int)$pid);
        $force = post_int('force') === 1;
        if ($existante && !$force) {
            // Doublon détecté : on rouvre le modal avec une alerte (PRG via session).
            $flash = ['red', 'Ce patient est déjà dans la file d\'attente aujourd\'hui ('
                . h(accueil_statut_label($existante['statut']))
                . '). Cochez « Forcer » pour ajouter quand même.'];
        } else {
            $aid = checkin_patient((int)$pid, (int)currentUser()['id'], $motif);
            if ($aid > 0) {
                logActivity('Accueil : check-in patient ID ' . $pid . ' (arrivée ' . $aid . ')', 'green', 'patient', (int)$pid);
                $flash = ['green', 'Patient ajouté à la file d\'attente.'];
            } else {
                $flash = ['red', 'Échec du check-in.'];
            }
        }
    }
}

// Les autres POST handlers seront ajoutés en Task 7-8.

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('accueil');

// --- Filtre statut (GET) ---
$statutFilter = in_whitelist(get_str('statut'), ['', 'arrive', 'constantes_prises', 'en_consultation', 'termine', 'parti'], '');

// --- File d'attente du jour ---
$arrivees = get_arrivees_du_jour($statutFilter ?: null);

// --- Stats du jour ---
$stats = [
    'total'      => 0, 'arrive' => 0, 'constantes' => 0,
    'consultation' => 0, 'termine' => 0,
];
foreach (get_arrivees_du_jour() as $a) {
    $stats['total']++;
    if ($a['statut'] === 'arrive') $stats['arrive']++;
    elseif ($a['statut'] === 'constantes_prises') $stats['constantes']++;
    elseif ($a['statut'] === 'en_consultation') $stats['consultation']++;
    elseif ($a['statut'] === 'termine') $stats['termine']++;
}

// --- Médecins actifs (pour modal orientation, Task 8) ---
$medecins = db_select("SELECT id, CONCAT(prenom,' ',nom) AS nom_complet, specialite FROM utilisateurs WHERE role='medecin' AND statut='actif' ORDER BY nom");

// --- Setting gating souple ---
$verifierConsultation = setting('accueil_verifier_consultation', '1') === '1';

// --- Patients récents (pour check-in existant, Task 6) ---
$patientsRecents = db_select("SELECT id, numero, nom, prenom FROM patients ORDER BY date_creation DESC LIMIT 50");

$statutBadge = [
    'arrive'            => 'badge-blue',
    'constantes_prises' => 'badge-yellow',
    'en_consultation'   => 'badge-purple',
    'termine'           => 'badge-green',
    'parti'             => 'badge-gray',
];
?>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= h($flash[1]) ?></div>
<?php endif; ?>

<div class="page-header-row">
  <div><h2>🏥 Accueil — Salle d'attente</h2><p><?= $stats['total'] ?> patient(s) aujourd'hui · <?= date('d/m/Y') ?></p></div>
  <div style="display:flex;gap:8px">
    <?php if (can('accueil.checkin')): ?>
    <button class="btn btn-blue" onclick="document.getElementById('modal-enregistrer').style.display='flex'">＋ Enregistrer un patient</button>
    <button class="btn btn-ghost" onclick="document.getElementById('modal-checkin').style.display='flex'">＋ Check-in patient existant</button>
    <?php endif; ?>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid mb-24">
  <div class="stat-card blue"><div class="stat-icon blue">👥</div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total aujourd'hui</div></div>
  <div class="stat-card yellow"><div class="stat-icon yellow">⏳</div><div class="stat-value"><?= $stats['arrive'] ?></div><div class="stat-label">En attente constantes</div></div>
  <div class="stat-card purple"><div class="stat-icon purple">🩺</div><div class="stat-value"><?= $stats['consultation'] ?></div><div class="stat-label">En consultation</div></div>
  <div class="stat-card green"><div class="stat-icon green">✅</div><div class="stat-value"><?= $stats['termine'] ?></div><div class="stat-label">Terminés</div></div>
</div>

<!-- Filtres statut -->
<div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
  <a href="accueil.php" class="btn btn-sm <?= $statutFilter===''?'btn-blue':'btn-ghost' ?>">Tous (<?= $stats['total'] ?>)</a>
  <?php foreach (['arrive'=>'Arrivé','constantes_prises'=>'Constantes prises','en_consultation'=>'En consultation','termine'=>'Terminé'] as $sv=>$sl): ?>
  <a href="accueil.php?statut=<?= $sv ?>" class="btn btn-sm <?= $statutFilter===$sv?'btn-blue':'btn-ghost' ?>"><?= $sl ?></a>
  <?php endforeach; ?>
</div>

<!-- File d'attente -->
<div class="card">
  <div class="card-header"><h3>File d'attente du jour</h3><span style="font-size:12px;color:var(--text2)"><?= count($arrivees) ?> patient(s)</span></div>
  <table>
    <thead><tr><th>Patient</th><th>Arrivée</th><th>Statut</th><th>Constantes</th><th>Médecin</th><th>Accès aux soins</th><th class="col-actions">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($arrivees as $a):
        $age = !empty($a['date_naissance']) ? (int)((time()-strtotime($a['date_naissance']))/31536000) : '?';
        $dossierUrl = secure_url('dossiers.php', (int)$a['patient_id'], 'dossier', ['patient_id' => (int)$a['patient_id']]);
    ?>
      <tr>
        <td>
          <a href="<?= h($dossierUrl) ?>" style="text-decoration:none"><strong><?= h($a['prenom'].' '.$a['nom']) ?></strong></a>
          <div style="font-size:11px;color:var(--text3)"><?= h($a['numero']) ?> · <?= $age ?> ans · <?= h($a['sexe']) ?></div>
        </td>
        <td><?= date('H:i', strtotime($a['date_arrivee'])) ?></td>
        <td><span class="badge <?= $statutBadge[$a['statut']] ?? 'badge-gray' ?>"><?= h(accueil_statut_label($a['statut'])) ?></span></td>
        <td><?= $a['statut']==='arrive' ? '<span class="badge badge-gray">—</span>' : '<span class="badge badge-green">✓ '.date('H:i', strtotime($a['date_constantes'])).'</span>' ?></td>
        <td><?= $a['medecin_nom'] ? h($a['medecin_nom']) : '<span style="color:var(--text3)">—</span>' ?></td>
        <td>
          <?php if ($verifierConsultation): ?>
            <?php if (!$a['a_dossier']): ?><span class="badge badge-yellow" title="Pas de dossier médical">📂 No dossier</span> <?php endif; ?>
            <?php if (!$a['a_consultation']): ?><span class="badge badge-yellow" title="Pas de droit de consultation payé">🧾 No consultation</span><?php endif; ?>
            <?php if ($a['a_dossier'] && $a['a_consultation']): ?><span class="badge badge-green">✓</span><?php endif; ?>
          <?php else: ?>
            <span style="color:var(--text3)">—</span>
          <?php endif; ?>
        </td>
        <td><div class="row-actions"><span class="row-hint" aria-hidden="true">⋯</span>
          <?php if (can('accueil.vitals') && in_array($a['statut'], ['arrive'], true)): ?>
          <button class="btn btn-sm btn-ghost" data-vitals="<?= (int)$a['id'] ?>" data-pid="<?= (int)$a['patient_id'] ?>" data-nom="<?= h($a['prenom'].' '.$a['nom']) ?>">🌡️ Constantes</button>
          <?php endif; ?>
          <?php if (can('accueil.orienter') && in_array($a['statut'], ['constantes_prises','arrive'], true)): ?>
          <button class="btn btn-sm btn-ghost" data-orienter="<?= (int)$a['id'] ?>" data-pid="<?= (int)$a['patient_id'] ?>" data-nom="<?= h($a['prenom'].' '.$a['nom']) ?>">🩺 Orienter</button>
          <?php endif; ?>
          <?php if (can('accueil.orienter') && in_array($a['statut'], ['en_consultation','constantes_prises','arrive'], true)): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Terminer la prise en charge de ce patient ?')">
            <input type="hidden" name="action" value="terminer">
            <input type="hidden" name="arrivee_id" value="<?= (int)$a['id'] ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-green" style="font-size:11px">✓</button>
          </form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($arrivees)): ?>
      <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text3)">Aucun patient dans la file d'attente. Cliquez sur « Enregistrer un patient ».</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- MODAL ENREGISTRER + CHECK-IN -->
<?php if (can('accueil.checkin')): ?>
<div id="modal-enregistrer" class="modal-overlay" role="dialog" aria-modal="true" style="display:none;z-index:200;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(620px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7);margin:auto">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:var(--surface);border-radius:16px 16px 0 0">
      <h3>＋ Enregistrer un patient à l'accueil</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('modal-enregistrer').style.display='none'" aria-label="Fermer" style="font-size:18px;color:var(--text2)">✕</button>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="create_and_checkin"><?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group"><label for="acc-nom">Nom *</label><input type="text" name="nom" id="acc-nom" required maxlength="100"></div>
        <div class="form-group"><label for="acc-prenom">Prénom *</label><input type="text" name="prenom" id="acc-prenom" required maxlength="100"></div>
        <div class="form-group"><label for="acc-naissance">Date de naissance *</label><input type="date" name="date_naissance" id="acc-naissance" required></div>
        <div class="form-group"><label for="acc-sexe">Sexe *</label>
          <select name="sexe" id="acc-sexe" required style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value="">--</option><option value="M">Masculin</option><option value="F">Féminin</option><option value="Autre">Autre</option>
          </select>
        </div>
        <div class="form-group"><label for="acc-tel">Téléphone</label><input type="text" name="telephone" id="acc-tel" maxlength="20"></div>
        <div class="form-group"><label for="acc-groupe">Groupe sanguin</label>
          <select name="groupe_sanguin" id="acc-groupe" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value="">Inconnu</option>
            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $gs): ?><option value="<?= $gs ?>"><?= $gs ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label for="acc-assurance">Assurance</label>
          <select name="assurance" id="acc-assurance" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <?php foreach (['CPAM'=>'CPAM','Mutuelle'=>'Mutuelle','Non assuré'=>'Non assuré','Étranger'=>'Étranger'] as $av=>$al): ?><option value="<?= $av ?>"><?= $al ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group form-full"><label for="acc-motif">Motif de l'arrivée *</label><input type="text" name="motif" id="acc-motif" required maxlength="255" placeholder="ex : Consultation, Douleur, Suivi..."></div>
        <div class="form-group form-full"><label for="acc-allergies">Allergies connues</label><input type="text" name="allergies" id="acc-allergies" maxlength="255" placeholder="ex : Pénicilline"></div>
        <div class="form-group form-full"><label for="acc-antecedents">Antécédents</label><input type="text" name="antecedents" id="acc-antecedents" maxlength="255"></div>
        <div class="form-group form-full"><label for="acc-adresse">Adresse</label><input type="text" name="adresse" id="acc-adresse"></div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-enregistrer').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">Enregistrer &amp; ajouter à la file</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- MODAL CHECK-IN EXISTANT -->
<?php if (can('accueil.checkin')): ?>
<div id="modal-checkin" class="modal-overlay" role="dialog" aria-modal="true" style="display:none;z-index:200;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(560px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7);margin:auto">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>＋ Check-in patient existant</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('modal-checkin').style.display='none'" aria-label="Fermer" style="font-size:18px;color:var(--text2)">✕</button>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="checkin"><?= csrf_field() ?>
      <div class="form-group form-full" style="margin-bottom:14px">
        <label for="chk-patient_id">Patient *</label>
        <select name="patient_id" id="chk-patient_id" required style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
          <option value="">-- Sélectionner --</option>
          <?php foreach ($patientsRecents as $pr): ?><option value="<?= (int)$pr['id'] ?>"><?= h($pr['prenom'].' '.$pr['nom']) ?> (<?= h($pr['numero']) ?>)</option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group form-full" style="margin-bottom:14px">
        <label for="chk-motif">Motif de l'arrivée</label>
        <input type="text" name="motif" id="chk-motif" maxlength="255" placeholder="ex : Consultation, Douleur, Suivi...">
      </div>
      <label style="display:flex;gap:8px;align-items:center;font-size:13px;color:var(--text2);margin-bottom:16px">
        <input type="checkbox" name="force" value="1"> Forcer le check-in si déjà présent aujourd'hui
      </label>
      <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-checkin').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">Ajouter à la file</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php';