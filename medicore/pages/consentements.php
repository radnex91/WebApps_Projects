<?php
$currentPage = 'consentements';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$flash = null;

// --- POST : Enregistrer un consentement ---
if (can('consentements.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'enregistrer') {
    csrf_verify();
    $patient_id   = post_int('patient_id');
    $type         = in_whitelist(post_str('type'), ['soins','partage_donnees','recherche','sortie_contre_avis','directives_anticipees'], 'soins');
    $statut       = in_whitelist(post_str('statut'), ['donne','refuse','retire','inapplicable'], 'donne');
    $doc_ref      = post_str('document_reference');
    $notes        = post_str('notes');

    if ($patient_id > 0) {
        db_exec("INSERT INTO consentements_patients (patient_id, utilisateur_id, type_consentement, statut, document_reference, notes) VALUES (?,?,?,?,?,?)",
            [$patient_id, currentUser()['id'], $type, $statut, $doc_ref, $notes]);
        logActivity("Consentement '$type' enregistre pour patient #$patient_id", 'blue', 'consentement');
        $flash = ['green', 'Consentement enregistre avec succes.'];
    }
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('consentements');

// --- Donnees ---
$patient_id = get_int('patient_id');
$search     = get_str('search');

$typeLabels = ['soins'=>'Consentement aux soins','partage_donnees'=>'Partage des donnees','recherche'=>'Recherche medicale','sortie_contre_avis'=>'Sortie contre avis medical','directives_anticipees'=>'Directives anticipees'];
$typeIcon   = ['soins'=>'🏥','partage_donnees'=>'📤','recherche'=>'🔬','sortie_contre_avis'=>'🚪','directives_anticipees'=>'📝'];
$statBadge  = ['donne'=>'badge-green','refuse'=>'badge-red','retire'=>'badge-yellow','inapplicable'=>'badge-gray'];
$statLabel  = ['donne'=>'Donne','refuse'=>'Refuse','retire'=>'Retire','inapplicable'=>'Inapplicable'];

// Patient selectionne
$patientSelect = null;
if ($patient_id > 0) $patientSelect = db_row("SELECT * FROM patients WHERE id=?", [$patient_id]);

// Consentements
$consentements = [];
if ($patient_id > 0) {
    $consentements = db_select("SELECT c.*, CONCAT(u.prenom,' ',u.nom) AS saisi_par
        FROM consentements_patients c
        LEFT JOIN utilisateurs u ON u.id=c.utilisateur_id
        WHERE c.patient_id=? ORDER BY c.date_consentement DESC LIMIT 100", [$patient_id]);
}

// Verifier consentements manquants
$manquants = [];
if ($patient_id > 0) {
    foreach (array_keys($typeLabels) as $tk) {
        $exist = (int)db_scalar("SELECT COUNT(*) FROM consentements_patients WHERE patient_id=? AND type_consentement=?", [$patient_id, $tk]);
        if ($exist == 0) $manquants[] = $tk;
    }
}

// Recherche patient
$resultats = [];
if ($search) {
    $like = "%$search%";
    $resultats = db_select("SELECT * FROM patients WHERE nom LIKE ? OR prenom LIKE ? OR numero LIKE ? LIMIT 20", [$like,$like,$like]);
}
?>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= h($flash[1]) ?></div>
<?php endif; ?>

<div class="page-header-row">
  <div><h2>📜 Consentements patients (RGPD)</h2></div>
</div>

<div class="card">
  <div style="padding:16px;border-bottom:1px solid var(--border);display:flex;gap:12px">
    <form method="GET" style="display:flex;gap:8px">
      <input type="search" name="search" placeholder="Rechercher un patient..." value="<?= h($search) ?>" style="max-width:260px">
      <button class="btn btn-sm btn-blue">🔍</button>
    </form>
    <?php if ($patient_id > 0): ?><a href="consentements.php" class="btn btn-sm btn-ghost">✕ Reset</a><?php endif; ?>
  </div>

  <?php if ($search && !$patient_id): ?>
  <table><thead><tr><th>Patient</th><th>N° Dossier</th><th></th></tr></thead><tbody>
  <?php foreach ($resultats as $r): ?>
  <tr><td><?= h($r['prenom'].' '.$r['nom']) ?></td><td><?= h($r['numero']) ?></td>
    <td><a href="consentements.php?patient_id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-blue">Selectionner</a></td></tr>
  <?php endforeach; ?></tbody></table>
  <?php endif; ?>
</div>

<?php if ($patient_id > 0 && $patientSelect): ?>
<!-- En-tete patient -->
<div class="card" style="margin-top:16px;border-left:4px solid var(--accent)">
  <div style="display:flex;align-items:center;gap:16px;padding:12px 16px">
    <div style="width:44px;height:44px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700"><?= h(strtoupper(mb_substr($patientSelect['prenom']??'',0,1).mb_substr($patientSelect['nom']??'',0,1))) ?></div>
    <div><strong><?= h($patientSelect['prenom'].' '.$patientSelect['nom']) ?></strong> <span style="font-size:12px;color:var(--text3)">· <?= h($patientSelect['numero']) ?></span></div>
    <?php if (can('consentements.create')): ?>
    <button class="btn btn-blue" style="margin-left:auto" onclick="document.getElementById('modal-consent').style.display='flex'">+ Consentement</button>
    <?php endif; ?>
  </div>
</div>

<!-- Alertes consentements manquants -->
<?php if (!empty($manquants)): ?>
<div class="alert alert-yellow" style="margin-top:12px">
  <strong>⚠️ Consentements manquants :</strong>
  <?php foreach ($manquants as $m): ?>
    <span class="badge badge-yellow" style="margin:2px 4px"><?= $typeLabels[$m] ?></span>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Tableau consentements -->
<div class="card" style="margin-top:16px">
  <table><thead><tr><th>Date</th><th>Type</th><th>Statut</th><th>Saisi par</th><th>Document</th><th>Notes</th></tr></thead><tbody>
  <?php foreach ($consentements as $c): ?>
  <tr><td><?= fmt_date($c['date_consentement'], true) ?></td>
    <td><?= $typeIcon[$c['type_consentement']]??'' ?> <?= $typeLabels[$c['type_consentement']]??$c['type_consentement'] ?></td>
    <td><span class="badge <?= $statBadge[$c['statut']]??'badge-gray' ?>"><?= $statLabel[$c['statut']]??$c['statut'] ?></span></td>
    <td><?= h($c['saisi_par']??'-') ?></td>
    <td><?= h($c['document_reference']??'-') ?></td>
    <td style="max-width:200px;color:var(--text2)"><?= h($c['notes']??'') ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($consentements)): ?>
  <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text3)">Aucun consentement enregistre pour ce patient.</td></tr>
  <?php endif; ?>
  </tbody></table>
</div>
<?php endif; ?>

<!-- Modal consentement -->
<?php if ($patient_id > 0 && can('consentements.create')): ?>
<div id="modal-consent" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:520px;max-width:95vw;box-shadow:0 24px 60px rgba(0,0,0,.7);max-height:90vh;overflow-y:auto">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>Enregistrer un consentement</h3><div onclick="document.getElementById('modal-consent').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)">✕</div></div>
    <form method="POST" style="padding:24px"><input type="hidden" name="action" value="enregistrer">
    <input type="hidden" name="patient_id" value="<?= $patient_id ?>"><?= csrf_field() ?>
    <div class="form-group"><label>Type de consentement *</label><select name="type" required>
      <option value="">-- Selectionner --</option>
      <?php foreach ($typeLabels as $tk=>$tl): ?><option value="<?= $tk ?>"><?= $typeIcon[$tk] ?> <?= $tl ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Statut *</label><select name="statut">
      <option value="donne">Donne</option><option value="refuse">Refuse</option><option value="retire">Retire</option><option value="inapplicable">Inapplicable</option></select></div>
    <div class="form-group"><label>Reference document</label><input type="text" name="document_reference" placeholder="Ex: Formulaire papier N°..."></div>
    <div class="form-group"><label>Notes</label><textarea name="notes" rows="2" placeholder="Precisions..."></textarea></div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
      <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-consent').style.display='none'">Annuler</button>
      <button type="submit" class="btn btn-blue">Enregistrer</button></div></form></div></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
