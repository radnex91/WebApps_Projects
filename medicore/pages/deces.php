<?php
$currentPage = 'deces';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$flash = null;

// --- POST : Etablir un certificat de deces ---
if (can('deces.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'certificat') {
    csrf_verify();
    $patient_id  = post_int('patient_id');
    $hosp_id     = post_int('hospitalisation_id');
    $date_deces  = post_str('date_deces');
    $cause       = post_str('cause');
    $code_cim10  = post_str('code_cim10');
    $type_deces  = in_whitelist(post_str('type_deces'), ['naturel','accidentel','suicide','indetermine','enquete'], 'naturel');
    $constat     = post_str('constat');
    $sortie      = in_whitelist(post_str('sortie'), ['non','famille','pompes_funebres','medecine_legale'], 'non');
    $date_sortie = post_str('date_sortie');
    $pf          = post_str('pompes_funebres');
    $notes       = post_str('notes');

    $v = (new Validator())->required($patient_id>0, 'patient', 'Patient requis.')
        ->required($date_deces, 'date', 'Date deces requise.')
        ->required($cause, 'cause', 'Cause du deces requise.');
    if (!$v->passes()) { $flash = ['red', $v->first_error()]; }
    else {
        db_exec("INSERT INTO certificats_deces (patient_id, hospitalisation_id, medecin_id, date_heure_deces, cause_deces, code_cim10, type_deces, constat, sortie_corps, date_sortie_corps, pompes_funebres, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [$patient_id, $hosp_id>0?$hosp_id:null, currentUser()['id'], $date_deces, $cause, $code_cim10?:null, $type_deces, $constat, $sortie, $date_sortie?:null, $pf?:null, $notes]);

        // Mettre a jour le statut de l'hospitalisation
        if ($hosp_id > 0) {
            db_exec("UPDATE hospitalisations SET statut='decede', date_sortie=? WHERE id=?", [$date_deces, $hosp_id]);
            db_exec("UPDATE lits SET statut='nettoyage' WHERE id=(SELECT lit_id FROM hospitalisations WHERE id=?)", [$hosp_id]);
        }
        logActivity("Certificat deces etabli : patient #$patient_id", 'red', 'deces');
        $flash = ['green', 'Certificat de deces enregistre.'];
    }
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('deces');

// --- Donnees ---
$certificats = db_select("SELECT cd.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom, p.numero AS patient_num, p.date_naissance, p.sexe,
    CONCAT(u.prenom,' ',u.nom) AS medecin_nom
    FROM certificats_deces cd
    JOIN patients p ON p.id=cd.patient_id
    JOIN utilisateurs u ON u.id=cd.medecin_id
    ORDER BY cd.date_heure_deces DESC LIMIT 100");

$stats = [
    'total_mois' => (int)db_scalar("SELECT COUNT(*) FROM certificats_deces WHERE MONTH(date_heure_deces)=MONTH(NOW())"),
    'total'      => (int)db_scalar("SELECT COUNT(*) FROM certificats_deces"),
    'en_attente' => (int)db_scalar("SELECT COUNT(*) FROM certificats_deces WHERE sortie_corps='non' AND date_heure_deces >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
    'decedes_hosp'=> (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE statut='decede' AND MONTH(date_sortie)=MONTH(NOW())"),
];

$typeLabel = ['naturel'=>'Naturel','accidentel'=>'Accidentel','suicide'=>'Suicide','indetermine'=>'Indetermine','enquete'=>'Enquete en cours'];
$sortieLabel = ['non'=>'Non','famille'=>'Famille','pompes_funebres'=>'Pompes funebres','medecine_legale'=>'Medecine legale'];
?>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= h($flash[1]) ?></div>
<?php endif; ?>

<div class="page-header-row">
  <div><h2>🕊️ Deces &amp; Thanatologie</h2><p><?= $stats['total_mois'] ?> deces (mois) · <?= $stats['en_attente'] ?> corps en attente</p></div>
  <?php if (can('deces.create')): ?>
  <button class="btn btn-red" onclick="document.getElementById('modal-deces').style.display='flex'">+ Certificat de deces</button>
  <?php endif; ?>
</div>

<div class="stats-grid mb-24">
  <div class="stat-card red"><div class="stat-icon red">🕊️</div><div class="stat-value"><?= $stats['total_mois'] ?></div><div class="stat-label">Deces (mois)</div></div>
  <div class="stat-card yellow"><div class="stat-icon yellow">⏳</div><div class="stat-value"><?= $stats['en_attente'] ?></div><div class="stat-label">Corps en attente</div></div>
  <div class="stat-card"><div class="stat-icon">📊</div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total certificats</div></div>
  <div class="stat-card"><div class="stat-icon">🏥</div><div class="stat-value"><?= $stats['decedes_hosp'] ?></div><div class="stat-label">Decedes hosp.(mois)</div></div>
</div>

<div class="card"><table>
  <thead><tr><th>Date deces</th><th>Patient</th><th>Age/Sexe</th><th>Cause</th><th>Type</th><th>Medecin</th><th>Sortie corps</th></tr></thead>
  <tbody>
  <?php foreach ($certificats as $d):
    $age = !empty($d['date_naissance']) ? (int)((strtotime($d['date_heure_deces'])-strtotime($d['date_naissance']))/31536000) : '?';
  ?>
    <tr style="border-left:3px solid var(--red)">
      <td><strong><?= fmt_date($d['date_heure_deces'], true) ?></strong></td>
      <td><?= h($d['patient_nom']) ?><div style="font-size:10px;color:var(--text3)"><?= h($d['patient_num']) ?></div></td>
      <td><?= $age ?> ans · <?= $d['sexe'] ?></td>
      <td><?= h($d['cause_deces']) ?><?= $d['code_cim10']?' <span style="font-size:10px;color:var(--text3)">['.$d['code_cim10'].']</span>':'' ?></td>
      <td><?= $typeLabel[$d['type_deces']]??$d['type_deces'] ?></td>
      <td style="font-size:12px"><?= h($d['medecin_nom']) ?></td>
      <td>
        <span class="badge <?= $d['sortie_corps']==='non'?'badge-yellow':'badge-green' ?>"><?= $sortieLabel[$d['sortie_corps']]??$d['sortie_corps'] ?></span>
        <?php if ($d['pompes_funebres']): ?><div style="font-size:10px;color:var(--text2)"><?= h($d['pompes_funebres']) ?></div><?php endif; ?>
      </td></tr>
  <?php endforeach; ?>
  <?php if (empty($certificats)): ?>
    <tr><td colspan="7" style="text-align:center;padding:48px;color:var(--text3)">Aucun certificat de deces enregistre.</td></tr>
  <?php endif; ?>
</tbody></table></div>

<!-- Modal certificat deces -->
<?php if (can('deces.create')): ?>
<div id="modal-deces" class="modal-overlay" style="display:none;z-index:200;align-items:center;justify-content:center" role="dialog" aria-modal="true" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(600px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7);max-height:90vh;overflow-y:auto">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>🕊️ Etablir un certificat de deces</h3><button type="button" class="modal-close" onclick="document.getElementById('modal-deces').style.display='none'" aria-label="Fermer" style="font-size:18px;color:var(--text2)">✕</button></div>
    <form method="POST" style="padding:24px"><input type="hidden" name="action" value="certificat"><?= csrf_field() ?>
    <div class="form-grid">
      <div class="form-group form-full"><label>Patient *</label><select name="patient_id" required onchange="chargerHospitalisations(this.value)"><option value="">-- --</option>
      <?php $patients = db_select("SELECT id, CONCAT(prenom,' ',nom) AS n, numero FROM patients ORDER BY nom LIMIT 100");
      foreach ($patients as $p): ?><option value="<?= (int)$p['id'] ?>"><?= h($p['n'].' - '.$p['numero']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group form-full"><label>Hospitalisation liee</label><select name="hospitalisation_id" id="deces-hosp"><option value="">Aucune / Non hospitalise</option></select></div>
      <div class="form-group form-full"><label>Date et heure du deces *</label><input type="datetime-local" name="date_deces" required></div>
      <div class="form-group form-full"><label>Cause du deces *</label><input type="text" name="cause" required placeholder="Ex: Arret cardio-respiratoire"></div>
      <div class="form-group"><label>Code CIM-10</label><input type="text" name="code_cim10" placeholder="Ex: I21.0"></div>
      <div class="form-group"><label>Type de deces</label><select name="type_deces"><?php foreach ($typeLabel as $tk=>$tl): ?><option value="<?= $tk ?>"><?= $tl ?></option><?php endforeach; ?></select></div>
      <div class="form-group form-full"><label>Constat medical</label><textarea name="constat" rows="2" placeholder="Constat medical du deces..."></textarea></div>
      <div class="form-group form-full"><label>Sortie du corps</label><select name="sortie"><option value="non">Pas encore</option><option value="famille">Remis a la famille</option><option value="pompes_funebres">Pompes funebres</option><option value="medecine_legale">Medecine legale</option></select></div>
      <div class="form-group"><label>Date sortie corps</label><input type="date" name="date_sortie"></div>
      <div class="form-group"><label>Pompes funebres</label><input type="text" name="pompes_funebres" placeholder="Nom des pompes funebres"></div>
      <div class="form-group form-full"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
      <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-deces').style.display='none'">Annuler</button>
      <button type="submit" class="btn btn-red">Etablir le certificat</button></div></form></div></div>
<script>
var hospParPatient={<?php $hospH = db_select("SELECT id, patient_id, statut FROM hospitalisations WHERE statut='en_cours'");
foreach ($hospH as $h) echo '"'.$h['patient_id'].'":"'.$h['id'].'",'; echo '"_":""'; ?>};
function chargerHospitalisations(pid){
  var s=document.getElementById('deces-hosp');
  s.innerHTML='<option value="">Aucune</option>';
  var hid=hospParPatient[pid];
  if(hid)s.innerHTML+='<option value="'+hid+'" selected>Hospitalisation active #'+hid+'</option>';
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
