<?php
$currentPage = 'urgences';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

//  METTRE À JOUR STATUT HOSPITALISATION
if (can('hospitalisations.update') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'update_hosp') {
    csrf_verify();
    $id     = post_int('hosp_id');
    $statut = in_whitelist(post_str('statut'),['en_cours','sorti','transfere'],'en_cours');
    $notes  = post_str('notes');
    assert_owns('hospitalisations', $id);
    $date_sortie = $statut === 'sorti' ? 'NOW()' : 'NULL';
    db_exec("UPDATE hospitalisations SET statut=?,notes=?,date_sortie=IF(?='sorti',NOW(),NULL) WHERE id=?",
        [$statut,$notes,$statut,$id]);
    if ($statut==='sorti') {
        $lit_id = db_scalar("SELECT lit_id FROM hospitalisations WHERE id=?",[$id]);
        db_exec("UPDATE lits SET statut='nettoyage' WHERE id=?",[$lit_id]);
    }
    logActivity("Urgence #$id -> $statut", $statut==='sorti'?'green':'blue', 'hospitalisation', $id);
    header('Location: '.APP_URL.'/urgences.php?ok=1'); exit;
}


//  NOUVELLE ADMISSION 
if (can('hospitalisations.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'admettre') {
    csrf_verify();
    $patient_id = post_int('patient_id');
    $medecin_id = post_int('medecin_id');
    $lit_id     = post_int('lit_id');
    $priorite   = in_whitelist(post_str('priorite'), ['critique','urgent','normal'], 'normal');
    $motif      = post_str('motif');
    if (!$patient_id || !$medecin_id || !$lit_id || !$motif) {
        $flash_err = 'Patient, mdecin, lit et motif sont obligatoires.';
    } else {
        // Vérifier lit libre
        $lit = db_row("SELECT * FROM lits WHERE id=? AND statut='libre'", [$lit_id]);
        if (!$lit) {
            $flash_err = 'Ce lit n\'est pas disponible.';
        } else {
            $dept_id = (int)$lit['departement_id'];
            db_exec(
                "INSERT INTO hospitalisations (patient_id, lit_id, medecin_id, departement_id, motif, priorite, statut)
                 VALUES (?,?,?,?,?,?,'en_cours')",
                [$patient_id, $lit_id, $medecin_id, $dept_id, $motif, $priorite]
            );
            db_exec("UPDATE lits SET statut='occupe' WHERE id=?", [$lit_id]);
            logActivity("Admission patient #$patient_id a $priorite", 'blue', 'hospitalisation');
            header('Location: ' . APP_URL . '/urgences.php?ok=admitted'); exit;
        }
    }
}

// --- POST : Triage ---
if (can('triage.update') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'trier') {
    csrf_verify();
    $hosp_id   = post_int('hospitalisation_id');
    $categorie = in_whitelist(post_str('categorie'), ['1_immediat','2_tres_urgent','3_urgent','4_standard','5_non_urgent'], '4_standard');
    $signes    = post_str('signes');
    $notes     = post_str('notes');

    if ($hosp_id > 0) {
        // Verifier si triage existe deja
        $exist = db_row("SELECT * FROM triage_urgences WHERE hospitalisation_id=?", [$hosp_id]);
        $temps_max = ['1_immediat'=>0,'2_tres_urgent'=>20,'3_urgent'=>60,'4_standard'=>120,'5_non_urgent'=>240];
        if ($exist) {
            db_exec("UPDATE triage_urgences SET categorie_triage=?, temps_attente_max=?, signes_cliniques=?, notes=? WHERE id=?",
                [$categorie, $temps_max[$categorie], $signes, $notes, $exist['id']]);
        } else {
            db_exec("INSERT INTO triage_urgences (hospitalisation_id, categorie_triage, temps_attente_max, infirmier_triage_id, signes_cliniques, notes) VALUES (?,?,?,?,?,?)",
                [$hosp_id, $categorie, $temps_max[$categorie], currentUser()['id'], $signes, $notes]);
        }

        // Mettre a jour la priorite dans hospitalisations
        $prio_map = ['1_immediat'=>'critique','2_tres_urgent'=>'critique','3_urgent'=>'urgent','4_standard'=>'normal','5_non_urgent'=>'normal'];
        db_exec("UPDATE hospitalisations SET priorite=? WHERE id=?", [$prio_map[$categorie], $hosp_id]);

        logActivity("Triage #$hosp_id -> ".$categorie, 'yellow', 'hospitalisation', $hosp_id);
        header('Location: '.APP_URL.'/urgences.php?ok=triaged'); exit;
    }
}

//  DONNÉES
require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('urgences');

$filtre = in_whitelist(get_str('filtre'),['','critique','urgent','normal','sorti'],'');
$where  = "WHERE 1=1";
$params = [];
if ($filtre === 'sorti') { $where .= " AND h.statut='sorti'"; }
elseif ($filtre) { $where .= " AND h.priorite=? AND h.statut='en_cours'"; $params[]=$filtre; }
else { $where .= " AND h.statut='en_cours'"; }

$hosps = db_select("SELECT h.*,
    CONCAT(p.prenom,' ',p.nom) AS patient_nom, p.date_naissance, p.sexe, p.groupe_sanguin, p.allergies, p.numero AS patient_num,
    CONCAT(u.prenom,' ',u.nom) AS medecin_nom, u.extension,
    d.nom AS dept_nom, l.numero AS lit_numero, l.type AS lit_type
    FROM hospitalisations h
    JOIN patients p ON p.id=h.patient_id
    JOIN utilisateurs u ON u.id=h.medecin_id
    JOIN departements d ON d.id=h.departement_id
    JOIN lits l ON l.id=h.lit_id
    $where ORDER BY FIELD(h.priorite,'critique','urgent','normal'), h.date_admission DESC
    LIMIT 100", $params);

// Charger les triages pour les hospitalisations affichees
$triage_map = [];
if (!empty($hosps)) {
    $hosp_ids = array_map(fn($h)=>$h['id'], $hosps);
    if (!empty($hosp_ids)) {
        $placeholders = implode(',', array_fill(0, count($hosp_ids), '?'));
        $triages = db_select("SELECT * FROM triage_urgences WHERE hospitalisation_id IN ($placeholders)", $hosp_ids);
        foreach ($triages as $t) { $triage_map[$t['hospitalisation_id']] = $t; }
    }
}

$triageLabels = ['1_immediat'=>'Immédiat (Rouge)','2_tres_urgent'=>'Très urgent (Orange)','3_urgent'=>'Urgent (Jaune)','4_standard'=>'Standard (Vert)','5_non_urgent'=>'Non urgent (Bleu)'];
$triageBadge = ['1_immediat'=>'badge-red','2_tres_urgent'=>'badge-red','3_urgent'=>'badge-yellow','4_standard'=>'badge-green','5_non_urgent'=>'badge-blue'];

// Donnes pour modal admission
$patients_list = db_select("SELECT id, CONCAT(prenom,' ',nom) AS nom_complet, numero FROM patients ORDER BY nom LIMIT 300");
$medecins_list = db_select("SELECT id, CONCAT(prenom,' ',nom) AS nom_complet, specialite FROM utilisateurs WHERE role='medecin' AND statut='actif' ORDER BY nom");
$lits_libres   = db_select("SELECT l.id, l.numero, d.nom AS dept_nom FROM lits l JOIN departements d ON d.id=l.departement_id WHERE l.statut='libre' ORDER BY d.nom, l.numero");

$stats = [
    'actifs'   => (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE statut='en_cours'"),
    'critique' => (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE statut='en_cours' AND priorite='critique'"),
    'urgent'   => (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE statut='en_cours' AND priorite='urgent'"),
    'sortis'   => (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE DATE(date_sortie)=CURDATE()"),
];

// Audit : acces au tableau des urgences (vue operationnelle patients)
db_exec("INSERT INTO audit_acces (utilisateur_id, patient_id, type_acces, entite, adresse_ip) VALUES (?,NULL,'consultation','urgences_vue',?)",
    [currentUser()['id'], $_SERVER['REMOTE_ADDR']??'']);

$prioColor = ['critique'=>'var(--red)','urgent'=>'var(--yellow)','normal'=>'var(--accent)'];
$prioBadge = ['critique'=>'badge-red','urgent'=>'badge-yellow','normal'=>'badge-blue'];
$statBadge = ['en_cours'=>'badge-yellow','sorti'=>'badge-green','transfere'=>'badge-blue'];
$statLabel = ['en_cours'=>'En cours','sorti'=>'Sorti','transfere'=>'Transféré'];
?>

<?php if (isset($_GET['ok'])): ?><div class="alert alert-green alert-auto"> Mis à jour.</div><?php endif; ?>

<?php if (!empty($flash_err)): ?>
<div class="alert alert-red alert-auto"> <?= h($flash_err) ?></div>
<?php endif; ?>
<?php if (isset($_GET['ok']) && $_GET['ok']==='admitted'): ?>
<div class="alert alert-green alert-auto"> Patient admis avec succès.</div>
<?php endif; ?>
<?php if (isset($_GET['ok']) && $_GET['ok']==='1'): ?>
<div class="alert alert-green alert-auto"> Mis à jour.</div>
<?php endif; ?>

<?php if ($stats['critique'] > 0): ?>
<div class="alert alert-red" style="margin-bottom:16px">
   <strong><?= $stats['critique'] ?> patient(s) en état critique</strong>  Intervention médicale immédiate requise.
</div>
<?php endif; ?>

<div class="page-header-row">
  <div><h2> Urgences & Hospitalisations</h2><p><?= $stats['actifs'] ?> actifs  <?= $stats['critique'] ?> critiques  <?= $stats['sortis'] ?> sortis aujourd'hui</p></div>
  <?php if (can('hospitalisations.create')): ?>
  <button class="btn btn-red" onclick="document.getElementById('modal-admission').style.display='flex'"> Nouvelle admission</button>
  <?php endif; ?>
</div>

<div class="stats-grid mb-24">
  <div class="stat-card red"><div class="stat-icon red">🚨</div><div class="stat-value"><?= $stats['critique'] ?></div><div class="stat-label">Cas critiques</div></div>
  <div class="stat-card yellow"><div class="stat-icon yellow">⚠️</div><div class="stat-value"><?= $stats['urgent'] ?></div><div class="stat-label">Cas urgents</div></div>
  <div class="stat-card blue"><div class="stat-icon blue">🏥</div><div class="stat-value"><?= $stats['actifs'] ?></div><div class="stat-label">Hospitaliss actifs</div></div>
  <div class="stat-card green"><div class="stat-icon green">✅</div><div class="stat-value"><?= $stats['sortis'] ?></div><div class="stat-label">Sortis aujourd'hui</div></div>
</div>

<!-- Filtres -->
<div class="pill-tabs">
  <?php foreach ([''=>'Tous actifs','critique'=>' Critiques','urgent'=>' Urgents','normal'=>' Normal','sorti'=>' Sortis'] as $val=>$lbl): ?>
  <a href="?filtre=<?= $val ?>" class="pill-tab <?= $filtre===$val?'active':'' ?>"><?= $lbl ?></a>
  <?php endforeach; ?>
</div>

<!-- Liste hospitalisations -->
<div style="display:flex;flex-direction:column;gap:10px">
<?php foreach ($hosps as $h):
  $age = date_diff(date_create($h['date_naissance']), date_create())->y;
  $duree = round((time()-strtotime($h['date_admission']))/3600);
  $dureeStr = $duree < 24 ? "$duree h" : round($duree/24).' j';
?>
<div class="card" style="border-left:4px solid <?= $prioColor[$h['priorite']]??'var(--accent)' ?>">
  <div style="padding:16px 20px;display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">

    <!-- Priorit + patient -->
    <div style="flex:1;min-width:200px">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
        <span class="badge <?= $prioBadge[$h['priorite']]??'badge-blue' ?>"><?= ucfirst($h['priorite']) ?></span>
        <span class="badge <?= $statBadge[$h['statut']]??'badge-gray' ?>"><?= $statLabel[$h['statut']]??$h['statut'] ?></span>
        <span style="font-size:11px;color:var(--text3)"> <?= $dureeStr ?></span>
      </div>
      <div style="font-size:15px;font-weight:700;margin-bottom:4px">
            <a href="dossiers.php?patient_id=<?= (int)$h['patient_id'] ?>" style="color:var(--text);text-decoration:none">
              <?= h($h['patient_nom']) ?>
            </a>
          </div>
      <div style="font-size:12px;color:var(--text2)">
        <?= $age ?> ans  <?= $h['sexe']==='M'?'H':'F' ?>
        <?php if ($h['groupe_sanguin']): ?>  Groupe <?= h($h['groupe_sanguin']) ?><?php endif; ?>
         <span style="color:var(--text3)"><?= h($h['patient_num']) ?></span>
      </div>
      <?php if ($h['allergies']): ?>
      <div style="font-size:11px;color:var(--red);margin-top:4px"> Allergies: <?= h($h['allergies']) ?></div>
      <?php endif; ?>
    </div>

    <!-- Triage -->
    <?php $triage = $triage_map[$h['id']] ?? null; ?>
    <div style="flex:1;min-width:180px">
      <div style="font-size:11px;color:var(--text3);margin-bottom:4px">
        TRIAGE
        <?php if (can('triage.update')): ?>
        <button class="btn btn-sm btn-ghost" style="font-size:10px;padding:2px 6px;margin-left:4px"
          onclick="openTriageModal(<?= (int)$h['id'] ?>,'<?= $triage['categorie_triage']??'4_standard' ?>','<?= addslashes(h($triage['signes_cliniques']??'')) ?>','<?= addslashes(h($triage['notes']??'')) ?>')">Modifier</button>
        <?php endif; ?>
      </div>
      <?php if ($triage): ?>
        <span class="badge <?= $triageBadge[$triage['categorie_triage']] ?? 'badge-gray' ?>"><?= $triageLabels[$triage['categorie_triage']]??'?' ?></span>
        <div style="font-size:11px;color:var(--text2);margin-top:2px">
          Attente max : <?= $triage['temps_attente_max'] ?> min ·
          Trié : <?= fmt_date($triage['heure_triage'], true) ?>
        </div>
      <?php else: ?>
      <div style="font-size:12px;color:var(--text3)">Non trié</div>
      <?php endif; ?>
    </div>

    <!-- Infos cliniques -->
    <div style="flex:1;min-width:180px">
      <div style="font-size:11px;color:var(--text3);margin-bottom:4px">MOTIF D'ADMISSION</div>
      <div style="font-size:13px;color:var(--text)"><?= h(mb_substr($h['motif'],0,120)).(mb_strlen($h['motif'])>120?'':'') ?></div>
    </div>

    <!-- Localisation + mdecin -->
    <div style="min-width:160px;text-align:right">
      <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:4px">
         <?= h($h['dept_nom']) ?>  Lit <?= h($h['lit_numero']) ?>
      </div>
      <div style="font-size:12px;color:var(--text2);margin-bottom:8px">
         <?= h($h['medecin_nom']) ?>
        <?php if ($h['extension']): ?>  Ext. <?= h($h['extension']) ?><?php endif; ?>
      </div>
      <div style="font-size:11px;color:var(--text3)">Admis : <?= fmt_date($h['date_admission'],true) ?></div>
    </div>

    <!-- Actions -->
    <div style="display:flex;flex-direction:column;gap:6px;min-width:120px">
      <button class="btn btn-sm btn-ghost"
        onclick="openUpdateModal(<?= (int)$h['id'] ?>,'<?= h(addslashes($h['statut'])) ?>',`<?= addslashes(h($h['notes']??'')) ?>`)">
         Mettre à jour
      </button>
      <?php if ($h['statut']==='en_cours'): ?>
      <form method="POST" style="margin:0">
        <input type="hidden" name="action" value="update_hosp">
        <input type="hidden" name="hosp_id" value="<?= (int)$h['id'] ?>">
        <input type="hidden" name="statut" value="sorti">
        <input type="hidden" name="notes" value="">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-green" style="width:100%" onclick="return confirm('Confirmer la sortie du patient ?')"> Sortie</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php if (empty($hosps)): ?>
<div style="text-align:center;padding:48px;color:var(--text3)"><div style="font-size:32px;margin-bottom:12px"></div><div>Aucune hospitalisation pour ce filtre.</div></div>
<?php endif; ?>
</div>

<!-- MODAL TRIAGE -->
<?php if (can('triage.update')): ?>
<div id="modal-triage" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:201;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:520px;max-width:95vw;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3> Triage du patient</h3>
      <div onclick="document.getElementById('modal-triage').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)">x</div>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="trier">
      <input type="hidden" name="hospitalisation_id" id="triage-hosp-id">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>Catégorie de triage *</label>
        <select name="categorie" id="triage-cat" required style="font-size:14px">
          <option value="1_immediat">1 - Immédiat (Rouge) - Prise en charge immédiate</option>
          <option value="2_tres_urgent">2 - Très urgent (Orange) - < 20 minutes</option>
          <option value="3_urgent">3 - Urgent (Jaune) - < 60 minutes</option>
          <option value="4_standard" selected>4 - Standard (Vert) - < 120 minutes</option>
          <option value="5_non_urgent">5 - Non urgent (Bleu) - < 240 minutes</option>
        </select>
      </div>
      <div class="form-group">
        <label>Signes cliniques observés</label>
        <textarea name="signes" id="triage-signes" rows="2" placeholder="Signes cliniques lors du triage..."></textarea>
      </div>
      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" id="triage-notes" rows="2" placeholder="Notes complémentaires..."></textarea>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-triage').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-yellow">Enregistrer le triage</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- MODAL UPDATE -->
<div id="modal-update" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:480px;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3> Mettre à jour l'hospitalisation</h3>
      <div onclick="document.getElementById('modal-update').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)"></div>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="update_hosp">
      <input type="hidden" name="hosp_id" id="upd-id">
      <?= csrf_field() ?>
      <div class="form-group" style="margin-bottom:14px"><label>Statut</label>
        <select name="statut" id="upd-statut" style="padding:9px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);width:100%;font-family:inherit;font-size:13px;outline:none">
          <option value="en_cours">🏥 En cours</option>
          <option value="sorti">✅ Sorti</option>
          <option value="transfere">Transféré</option>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:20px"><label>Notes / Observations</label>
        <textarea name="notes" id="upd-notes" rows="4" style="width:100%;padding:9px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;resize:vertical"></textarea>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-update').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">💾 Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
function openUpdateModal(id, statut, notes) {
  document.getElementById('upd-id').value     = id;
  document.getElementById('upd-statut').value = statut;
  document.getElementById('upd-notes').value  = notes;
  document.getElementById('modal-update').style.display = 'flex';
}
function openTriageModal(hospId, cat, signes, notes) {
  document.getElementById('triage-hosp-id').value = hospId;
  document.getElementById('triage-cat').value     = cat;
  document.getElementById('triage-signes').value  = signes;
  document.getElementById('triage-notes').value   = notes;
  document.getElementById('modal-triage').style.display = 'flex';
}
</script>

<!-- MODAL NOUVELLE ADMISSION -->
<?php if (can('hospitalisations.create')): ?>
<div id="modal-admission" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:600px;box-shadow:0 24px 60px rgba(0,0,0,.7);margin:auto">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:var(--surface);border-radius:16px 16px 0 0">
      <h3> Nouvelle admission</h3>
      <div onclick="this.closest('[id]').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)"></div>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="admettre">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group form-full">
          <label>Patient *</label>
          <select name="patient_id" required style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value=""> Sélectionner un patient </option>
            <?php foreach ($patients_list as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= h($p['nom_complet']) ?> (<?= h($p['numero']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Médecin responsable *</label>
          <select name="medecin_id" required style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value=""> Sélectionner </option>
            <?php foreach ($medecins_list as $m): ?>
            <option value="<?= (int)$m['id'] ?>"><?= h($m['nom_complet']) ?><?= $m['specialite'] ? '  '.h($m['specialite']) : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Priorit *</label>
          <select name="priorite" required style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value="normal"> Normal</option>
            <option value="urgent"> Urgent</option>
            <option value="critique"> Critique</option>
          </select>
        </div>
        <div class="form-group form-full">
          <label>Lit d'admission *</label>
          <select name="lit_id" required style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value=""> Sélectionner un lit libre </option>
            <?php foreach ($lits_libres as $l): ?>
            <option value="<?= (int)$l['id'] ?>">Lit <?= h($l['numero']) ?>  <?= h($l['dept_nom']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($lits_libres)): ?>
          <small style="color:var(--red);font-size:11px;margin-top:4px;display:block"> Aucun lit libre disponible actuellement.</small>
          <?php endif; ?>
        </div>
        <div class="form-group form-full">
          <label>Motif d'admission *</label>
          <textarea name="motif" rows="3" required maxlength="500" style="width:100%;padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;resize:vertical" placeholder="Décrire le motif d'admission, symptômes, circonstances..."></textarea>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-admission').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-red" <?= empty($lits_libres)?'disabled title="Aucun lit disponible"':'' ?>> Admettre le patient</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php';
