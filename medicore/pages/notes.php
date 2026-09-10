<?php
$currentPage = 'notes';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$flash = null;

// --- POST : Creer une note clinique ---
if (can('notes.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'creer') {
    csrf_verify();
    $patient_id = post_int('patient_id');
    $hosp_id    = post_int('hospitalisation_id');
    $type_note  = in_whitelist(post_str('type_note'), ['admission','suivi','transmission','cr_sortie','cr_operatoire','consultation','autre'], 'suivi');
    $titre      = post_str('titre');
    $subjective = post_str('subjective');
    $objective  = post_str('objective');
    $analyse    = post_str('analyse');
    $plan       = post_str('plan');
    $contenu    = post_str('contenu');

    $v = (new Validator())->required($patient_id > 0, 'patient', 'Patient requis.');
    if (!$v->passes()) { $flash = ['red', $v->first_error()]; }
    else {
        db_exec("INSERT INTO notes_cliniques (patient_id, hospitalisation_id, utilisateur_id, type_note, titre, subjective, objective, analyse, plan, contenu) VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$patient_id, $hosp_id > 0 ? $hosp_id : null, currentUser()['id'], $type_note, $titre, $subjective, $objective, $analyse, $plan, $contenu]);
        logActivity("Note clinique : $titre", 'blue', 'note');
        $flash = ['green', 'Note clinique enregistrée.'];
    }
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('notes');

// --- Donnees ---
$patient_id = get_int('patient_id');
$filtre_type = in_whitelist(get_str('filtre_type'), ['','admission','suivi','transmission','cr_sortie','cr_operatoire','consultation','autre'], '');
$search = get_str('search');

// --- Patient selectionne ---
$patientSelect = null;
if ($patient_id > 0) {
    $patientSelect = db_row("SELECT * FROM patients WHERE id=?", [$patient_id]);
}

// --- Hospitalisation active ---
$hospActive = null;
if ($patient_id > 0) {
    $hospActive = db_row("SELECT h.*, d.nom AS dept_nom FROM hospitalisations h
        JOIN lits l ON l.id=h.lit_id JOIN departements d ON d.id=h.departement_id
        WHERE h.patient_id=? AND h.statut='en_cours'", [$patient_id]);
}

// --- Notes cliniques ---
$notes = [];
if ($patient_id > 0) {
    $where = "n.patient_id=?";
    $params = [$patient_id];
    if ($filtre_type) { $where .= " AND n.type_note=?"; $params[] = $filtre_type; }
    $notes = db_select("SELECT n.*, CONCAT(u.prenom,' ',u.nom) AS auteur_nom, u.role AS auteur_role
        FROM notes_cliniques n
        JOIN utilisateurs u ON u.id=n.utilisateur_id
        WHERE $where ORDER BY n.date_note DESC LIMIT 200", $params);
}

// --- Recherche patient ---
$resultats = [];
if ($search && !$patient_id) {
    $like = "%$search%";
    $resultats = db_select("SELECT * FROM patients WHERE nom LIKE ? OR prenom LIKE ? OR numero LIKE ? ORDER BY nom LIMIT 20", [$like,$like,$like]);
}

$typeLabels = ['admission'=>'Note d\'admission','suivi'=>'Note de suivi','transmission'=>'Transmission','cr_sortie'=>'CR de sortie','cr_operatoire'=>'CR opératoire','consultation'=>'Consultation','autre'=>'Autre'];
$typeIcon = ['admission'=>'🏥','suivi'=>'📋','transmission'=>'🔄','cr_sortie'=>'📤','cr_operatoire'=>'🔪','consultation'=>'👨‍⚕️','autre'=>'📝'];
?>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= h($flash[1]) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-header"><h3>📝 Notes cliniques &amp; Dossier de soins</h3></div>
  <form method="GET" style="padding:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;border-bottom:1px solid var(--border)">
    <input type="search" name="search" placeholder="Rechercher un patient..." value="<?= h($search) ?>" style="max-width:300px">
    <button type="submit" class="btn btn-sm btn-blue">🔍</button>
    <?php if ($patient_id > 0): ?><a href="notes.php" class="btn btn-sm btn-ghost">✕ Reset</a><?php endif; ?>
  </form>

  <?php if ($search && !$patient_id): ?>
  <table><thead><tr><th>Patient</th><th>N° Dossier</th><th></th></tr></thead><tbody>
  <?php foreach ($resultats as $r): ?>
    <tr><td><strong><?= h($r['prenom'].' '.$r['nom']) ?></strong></td><td><?= h($r['numero']) ?></td>
    <td><a href="notes.php?patient_id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-blue">Selectionner</a></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>
</div>

<?php if ($patient_id > 0 && $patientSelect): ?>
<!-- Header patient -->
<div class="card" style="border-left:4px solid var(--accent);margin-top:16px">
  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div style="width:48px;height:48px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px">
      <?= h(strtoupper(mb_substr($patientSelect['prenom']??'',0,1).mb_substr($patientSelect['nom']??'',0,1))) ?>
    </div>
    <div>
      <div style="font-size:16px;font-weight:700"><?= h($patientSelect['prenom'].' '.$patientSelect['nom']) ?></div>
      <div style="font-size:12px;color:var(--text2)"><?= h($patientSelect['numero']) ?> · N° Dossier</div>
    </div>
    <?php if ($hospActive): ?><span class="badge badge-blue" style="margin-left:auto"><?= h($hospActive['dept_nom']) ?></span><?php endif; ?>
    <?php if (can('notes.create')): ?>
    <button class="btn btn-blue" onclick="document.getElementById('modal-note').style.display='flex'">+ Nouvelle note</button>
    <?php endif; ?>
  </div>
</div>

<!-- Filtres type -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin:16px 0">
  <?php foreach ($typeLabels as $tk=>$tl): ?>
  <a href="?patient_id=<?= $patient_id ?>&filtre_type=<?= $tk ?>" class="badge <?= $filtre_type===$tk ? 'badge-blue' : 'badge-gray' ?>" style="text-decoration:none"><?= $typeIcon[$tk] ?> <?= $tl ?></a>
  <?php endforeach; ?>
  <?php if ($filtre_type): ?><a href="?patient_id=<?= $patient_id ?>" class="badge badge-red" style="text-decoration:none">✕ Tout</a><?php endif; ?>
</div>

<!-- Liste des notes -->
<?php foreach ($notes as $note): ?>
<div class="card" style="margin-bottom:16px;border-left:3px solid <?= ['admission'=>'var(--blue)','suivi'=>'var(--green)','transmission'=>'var(--yellow)','cr_sortie'=>'var(--accent)','cr_operatoire'=>'var(--red)','consultation'=>'var(--purple)','autre'=>'var(--text3)'][$note['type_note']] ?? 'var(--border)' ?>">
  <div class="card-header">
    <span><?= $typeIcon[$note['type_note']]??'📝' ?> <strong><?= $typeLabels[$note['type_note']]??h($note['type_note']) ?></strong></span>
    <?php if (!empty($note['titre'])): ?><span style="color:var(--text2);font-weight:600"><?= h($note['titre']) ?></span><?php endif; ?>
    <span style="margin-left:auto;font-size:12px;color:var(--text3)">
      <?= fmt_date($note['date_note'], true) ?> — <?= h($note['auteur_nom']) ?> (<?= h($note['auteur_role']) ?>)
    </span>
  </div>

  <?php if ($note['type_note'] === 'suivi' || $note['type_note'] === 'admission' || $note['type_note'] === 'consultation'): ?>
  <!-- Affichage SOAP -->
  <table style="width:100%;border-collapse:collapse">
    <?php if (!empty($note['subjective'])): ?>
    <tr><td style="width:80px;font-weight:700;color:var(--accent2);padding:8px 12px;vertical-align:top;font-size:12px">SUBJECTIF</td>
    <td style="padding:8px 12px;color:var(--text)"><?= nl2br(h($note['subjective'])) ?></td></tr>
    <?php endif; ?>
    <?php if (!empty($note['objective'])): ?>
    <tr><td style="font-weight:700;color:var(--green);padding:8px 12px;vertical-align:top;font-size:12px">OBJECTIF</td>
    <td style="padding:8px 12px;color:var(--text)"><?= nl2br(h($note['objective'])) ?></td></tr>
    <?php endif; ?>
    <?php if (!empty($note['analyse'])): ?>
    <tr><td style="font-weight:700;color:var(--yellow);padding:8px 12px;vertical-align:top;font-size:12px">ANALYSE</td>
    <td style="padding:8px 12px;color:var(--text)"><?= nl2br(h($note['analyse'])) ?></td></tr>
    <?php endif; ?>
    <?php if (!empty($note['plan'])): ?>
    <tr><td style="font-weight:700;color:var(--blue);padding:8px 12px;vertical-align:top;font-size:12px">PLAN</td>
    <td style="padding:8px 12px;color:var(--text)"><?= nl2br(h($note['plan'])) ?></td></tr>
    <?php endif; ?>
  </table>
  <?php endif; ?>

  <?php if (!empty($note['contenu'])): ?>
  <div style="padding:12px;color:var(--text);white-space:pre-wrap;border-top:1px solid var(--border)"><?= h($note['contenu']) ?></div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if (empty($notes)): ?>
<div style="text-align:center;padding:48px;color:var(--text3)">Aucune note clinique enregistrée pour ce patient.</div>
<?php endif; ?>
<?php endif; ?>

<!-- Modal nouvelle note -->
<?php if ($patient_id > 0 && can('notes.create')): ?>
<div id="modal-note" class="modal-overlay" role="dialog" aria-modal="true" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(700px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7);max-height:90vh;overflow-y:auto">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>Nouvelle note clinique</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('modal-note').style.display='none'" aria-label="Fermer">✕</button>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="creer">
      <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
      <?php if ($hospActive): ?><input type="hidden" name="hospitalisation_id" value="<?= (int)$hospActive['id'] ?>"><?php endif; ?>
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group">
          <label>Type de note *</label>
          <select name="type_note" id="note-type" onchange="toggleSoapFields()">
            <?php foreach ($typeLabels as $tk=>$tl): ?>
            <option value="<?= $tk ?>"><?= $tl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Titre</label><input type="text" name="titre" maxlength="255" placeholder="Ex: Suivi post-op J2"></div>
      </div>
      <div id="soap-fields">
        <div class="form-group"><label style="color:var(--accent2)">S - Subjectif</label><textarea name="subjective" rows="2" placeholder="Ce que dit le patient, symptomes decrits..."></textarea></div>
        <div class="form-group"><label style="color:var(--green)">O - Objectif</label><textarea name="objective" rows="2" placeholder="Examens cliniques, constantes, resultats..."></textarea></div>
        <div class="form-group"><label style="color:var(--yellow)">A - Analyse</label><textarea name="analyse" rows="2" placeholder="Diagnostic, hypotheses, evaluation..."></textarea></div>
        <div class="form-group"><label style="color:var(--blue)">P - Plan</label><textarea name="plan" rows="2" placeholder="Conduite a tenir, prescriptions, examens a prevoir..."></textarea></div>
      </div>
      <div class="form-group"><label>Contenu libre</label><textarea name="contenu" rows="3" placeholder="Ou rediger librement (hors format SOAP)..."></textarea></div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-note').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<script>
function toggleSoapFields(){
    var t=document.getElementById('note-type').value;
    var showSoap=['suivi','admission','consultation'].indexOf(t)>=0;
    document.getElementById('soap-fields').style.display=showSoap?'block':'none';
}
toggleSoapFields();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
