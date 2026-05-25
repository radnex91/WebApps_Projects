<?php
$currentPage = 'assurances';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$flash = null;

// --- POST : Creer/modifier une compagnie ---
if (can('assurances.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'save_compagnie') {
    csrf_verify();
    $id    = post_int('compagnie_id');
    $nom   = post_str('nom');
    $code  = post_str('code');
    $adr   = post_str('adresse');
    $tel   = post_str('telephone');
    $email = post_email('email');
    $taux  = post_float('taux', 80.0);
    $notes = post_str('notes');

    $v = (new Validator())->required($nom, 'nom', 'Nom requis.')->required($code, 'code', 'Code requis.');
    if (!$v->passes()) { $flash = ['red', $v->first_error()]; }
    elseif ($id > 0) {
        db_exec("UPDATE compagnies_assurance SET nom=?, code=?, adresse=?, telephone=?, email=?, taux_prise_en_charge=?, notes=? WHERE id=?",
            [$nom, $code, $adr, $tel, $email, $taux, $notes, $id]);
        $flash = ['green', 'Compagnie mise a jour.'];
    } else {
        db_exec("INSERT INTO compagnies_assurance (nom, code, adresse, telephone, email, taux_prise_en_charge, notes) VALUES (?,?,?,?,?,?,?)",
            [$nom, $code, $adr, $tel, $email, $taux, $notes]);
        $flash = ['green', 'Compagnie ajoutee.'];
    }
}

// --- POST : Demander une prise en charge ---
if (can('assurances.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'demander_pec') {
    csrf_verify();
    $patient_id   = post_int('patient_id');
    $compagnie_id = post_int('compagnie_id');
    $facture_id   = post_int('facture_id');
    $montant      = post_float('montant');
    $notes        = post_str('notes');

    if ($patient_id > 0 && $compagnie_id > 0 && $montant > 0) {
        $comp = db_row("SELECT * FROM compagnies_assurance WHERE id=?", [$compagnie_id]);
        $taux = $comp['taux_prise_en_charge'] ?? 80;
        db_exec("INSERT INTO prises_en_charge (patient_id, compagnie_id, facture_id, montant_demande, taux_couvert, notes) VALUES (?,?,?,?,?,?)",
            [$patient_id, $compagnie_id, $facture_id>0?$facture_id:null, $montant, $taux, $notes]);
        logActivity("PEC demandee : patient #$patient_id", 'blue', 'assurance');
        $flash = ['green', 'Prise en charge demandee.'];
    }
}

// --- POST : Mettre a jour statut PEC ---
if (can('assurances.update') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'update_pec') {
    csrf_verify();
    $pec_id    = post_int('pec_id');
    $statut    = in_whitelist(post_str('statut'), ['en_attente','accorde_partiel','accorde_total','refuse'], 'accorde_total');
    $no_accord = post_str('numero_accord');
    $mt_accorde = post_float('montant_accorde');
    $motif     = post_str('motif');

    if ($pec_id > 0) {
        db_exec("UPDATE prises_en_charge SET statut=?, date_accord=NOW(), numero_accord=?, montant_accorde=?, motif_refus=? WHERE id=?",
            [$statut, $no_accord, $statut==='refuse'?null:$mt_accorde, $statut==='refuse'?$motif:null, $pec_id]);
        logActivity("PEC #$pec_id -> $statut", $statut==='refuse'?'red':'green', 'assurance', $pec_id);
        $flash = ['green', 'Prise en charge mise a jour.'];
    }
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('assurances');

// --- Donnees ---
$tab = in_whitelist(get_str('tab'), ['compagnies','pec'], 'pec');

$compagnies = db_select("SELECT * FROM compagnies_assurance WHERE statut='actif' ORDER BY nom");
$pecs = db_select("SELECT pc.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom, p.numero AS patient_num,
    ca.nom AS compagnie_nom
    FROM prises_en_charge pc
    JOIN patients p ON p.id=pc.patient_id
    JOIN compagnies_assurance ca ON ca.id=pc.compagnie_id
    ORDER BY FIELD(pc.statut,'demande','en_attente','accorde_partiel','accorde_total','refuse'), pc.date_demande DESC LIMIT 200");

$stats = [
    'total_demandes' => (int)db_scalar("SELECT COUNT(*) FROM prises_en_charge WHERE statut IN ('demande','en_attente')"),
    'accordees'      => (int)db_scalar("SELECT COUNT(*) FROM prises_en_charge WHERE statut IN ('accorde_total','accorde_partiel') AND MONTH(date_accord)=MONTH(NOW())"),
    'refusees'       => (int)db_scalar("SELECT COUNT(*) FROM prises_en_charge WHERE statut='refuse' AND MONTH(date_demande)=MONTH(NOW())"),
    'total_mois'     => (float)db_scalar("SELECT COALESCE(SUM(montant_accorde),0) FROM prises_en_charge WHERE statut IN ('accorde_total','accorde_partiel') AND MONTH(date_accord)=MONTH(NOW())"),
];

$pecStatBadge = ['demande'=>'badge-yellow','en_attente'=>'badge-yellow','accorde_partiel'=>'badge-blue','accorde_total'=>'badge-green','refuse'=>'badge-red'];
$pecStatLabel = ['demande'=>'Demandee','en_attente'=>'En attente','accorde_partiel'=>'Accord partiel','accorde_total'=>'Accord total','refuse'=>'Refusee'];
?>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= h($flash[1]) ?></div>
<?php endif; ?>

<div class="page-header-row">
  <div><h2>📑 Assurances &amp; Tiers-payant</h2><p><?= $stats['total_demandes'] ?> demandes en cours</p></div>
</div>

<div class="stats-grid mb-24">
  <div class="stat-card yellow"><div class="stat-icon">⏳</div><div class="stat-value"><?= $stats['total_demandes'] ?></div><div class="stat-label">Demandes en cours</div></div>
  <div class="stat-card green"><div class="stat-icon">✅</div><div class="stat-value"><?= $stats['accordees'] ?></div><div class="stat-label">Accords (mois)</div></div>
  <div class="stat-card red"><div class="stat-icon">❌</div><div class="stat-value"><?= $stats['refusees'] ?></div><div class="stat-label">Refus (mois)</div></div>
  <div class="stat-card blue"><div class="stat-icon">💰</div><div class="stat-value"><?= fmt_money((float)$stats['total_mois']) ?></div><div class="stat-label">Total accordé (mois)</div></div>
</div>

<!-- Tabs -->
<div class="pill-tabs mb-16">
  <a href="?tab=pec" class="pill-tab <?= $tab==='pec'?'active':'' ?>">Prises en charge</a>
  <a href="?tab=compagnies" class="pill-tab <?= $tab==='compagnies'?'active':'' ?>">Compagnies d'assurance</a>
</div>

<?php if ($tab === 'compagnies'): ?>
<!-- Gestion compagnies -->
<?php if (can('assurances.create')): ?>
<button class="btn btn-blue mb-16" onclick="document.getElementById('modal-compagnie').style.display='flex'">+ Ajouter une compagnie</button>
<?php endif; ?>
<div class="card"><table>
  <thead><tr><th>Code</th><th>Nom</th><th>Contact</th><th>Taux PEC</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($compagnies as $c): ?>
  <tr><td><strong><?= h($c['code']) ?></strong></td>
    <td><?= h($c['nom']) ?></td>
    <td><?= h($c['telephone']??'') ?> <?= h($c['email']??'') ?></td>
    <td><strong><?= $c['taux_prise_en_charge'] ?>%</strong></td>
    <td><?php if (can('assurances.create')): ?><button class="btn btn-sm btn-ghost" onclick="editCompagnie(<?= htmlspecialchars(json_encode($c)) ?>)">✏️</button><?php endif; ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<!-- Modal compagnie -->
<?php if (can('assurances.create')): ?>
<div id="modal-compagnie" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:500px;max-width:95vw;box-shadow:0 24px 60px rgba(0,0,0,.7);max-height:90vh;overflow-y:auto">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3 id="comp-title">Ajouter une compagnie</h3><div onclick="closeCompagnie()" style="cursor:pointer;font-size:18px;color:var(--text2)">✕</div></div>
    <form method="POST" style="padding:24px"><input type="hidden" name="action" value="save_compagnie">
    <input type="hidden" name="compagnie_id" id="comp-id"><?= csrf_field() ?>
    <div class="form-grid">
      <div class="form-group"><label>Code *</label><input name="code" id="comp-code" required></div>
      <div class="form-group"><label>Nom *</label><input name="nom" id="comp-nom" required></div>
      <div class="form-group"><label>Telephone</label><input name="telephone" id="comp-tel"></div>
      <div class="form-group"><label>Email</label><input name="email" type="email" id="comp-email"></div>
      <div class="form-group form-full"><label>Adresse</label><input name="adresse" id="comp-adr"></div>
      <div class="form-group"><label>Taux PEC (%)</label><input type="number" name="taux" id="comp-taux" value="80" step="0.1" min="0" max="100"></div>
      <div class="form-group form-full"><label>Notes</label><textarea name="notes" id="comp-notes" rows="2"></textarea></div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
      <button type="button" class="btn btn-ghost" onclick="closeCompagnie()">Annuler</button>
      <button type="submit" class="btn btn-blue">Enregistrer</button></div></form></div></div>
<script>
function closeCompagnie(){document.getElementById('modal-compagnie').style.display='none';}
function editCompagnie(c){
  document.getElementById('comp-id').value=c.id;document.getElementById('comp-code').value=c.code;
  document.getElementById('comp-nom').value=c.nom;document.getElementById('comp-tel').value=c.telephone||'';
  document.getElementById('comp-email').value=c.email||'';document.getElementById('comp-adr').value=c.adresse||'';
  document.getElementById('comp-taux').value=c.taux_prise_en_charge;
  document.getElementById('comp-notes').value=c.notes||'';
  document.getElementById('comp-title').textContent='Modifier une compagnie';
  document.getElementById('modal-compagnie').style.display='flex';
}
</script>
<?php endif; ?>

<?php else: ?>
<!-- Tableau PEC -->
<?php if (can('assurances.create')): ?>
<button class="btn btn-blue mb-16" onclick="document.getElementById('modal-pec').style.display='flex'">+ Demander une prise en charge</button>
<?php endif; ?>

<div class="card"><table><thead><tr><th>Date</th><th>Patient</th><th>Compagnie</th><th>Montant</th><th>Taux</th><th>N° Accord</th><th>Statut</th><th></th></tr></thead><tbody>
<?php foreach ($pecs as $p): ?>
<tr><td style="font-size:12px"><?= fmt_date($p['date_demande']) ?></td>
  <td><?= h($p['patient_nom']) ?><div style="font-size:10px;color:var(--text3)"><?= h($p['patient_num']) ?></div></td>
  <td><?= h($p['compagnie_nom']) ?></td>
  <td><?= fmt_money((float)$p['montant_demande']) ?></td>
  <td><?= $p['taux_couvert'] ?>%</td>
  <td><?= h($p['numero_accord'] ?? '-') ?></td>
  <td><span class="badge <?= $pecStatBadge[$p['statut']] ?>"><?= $pecStatLabel[$p['statut']] ?></span></td>
  <td><?php if (can('assurances.update') && in_array($p['statut'],['demande','en_attente'])): ?>
    <button class="btn btn-sm btn-green" onclick="openUpdatePec(<?= (int)$p['id'] ?>,'<?= h(addslashes(fmt_money((float)$p['montant_demande']))) ?>')">Traiter</button>
  <?php endif; ?></td></tr>
<?php if ($p['motif_refus']): ?><tr><td colspan="8" style="color:var(--red);font-size:11px;padding:2px 12px">Refus : <?= h($p['motif_refus']) ?></td></tr><?php endif; ?>
<?php endforeach; ?>
<?php if (empty($pecs)): ?><tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text3)">Aucune prise en charge.</td></tr><?php endif; ?>
</tbody></table></div>

<!-- Modal demande PEC -->
<?php if (can('assurances.create')): ?>
<div id="modal-pec" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:500px;max-width:95vw;box-shadow:0 24px 60px rgba(0,0,0,.7);max-height:90vh;overflow-y:auto">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>Demande de prise en charge</h3><div onclick="document.getElementById('modal-pec').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)">✕</div></div>
    <form method="POST" style="padding:24px"><input type="hidden" name="action" value="demander_pec"><?= csrf_field() ?>
    <div class="form-group"><label>Patient *</label><select name="patient_id" required><option value="">-- --</option>
    <?php $plist = db_select("SELECT id, CONCAT(prenom,' ',nom) AS n, numero FROM patients WHERE assurance!='Non assure' ORDER BY nom LIMIT 100");
    foreach ($plist as $p): ?><option value="<?= (int)$p['id'] ?>"><?= h($p['n'].' - '.$p['numero']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Compagnie *</label><select name="compagnie_id" required><option value="">-- --</option>
    <?php foreach ($compagnies as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['nom'].' ('.$c['taux_prise_en_charge'].'%)') ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Montant demande *</label><input type="number" name="montant" step="0.01" required></div>
    <div class="form-group"><label>Facture liee</label><select name="facture_id"><option value="">-- --</option>
    <?php $factures = db_select("SELECT id, numero, montant_total FROM factures WHERE statut IN ('en_attente','impayee') LIMIT 50");
    foreach ($factures as $f): ?><option value="<?= (int)$f['id'] ?>"><?= h($f['numero'].' - '.fmt_money((float)$f['montant_total'])) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
      <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-pec').style.display='none'">Annuler</button>
      <button type="submit" class="btn btn-blue">Soumettre</button></div></form></div></div>
<?php endif; ?>

<!-- Modal traiter PEC -->
<?php if (can('assurances.update')): ?>
<div id="modal-traiter-pec" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:480px;max-width:95vw;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>Traiter la prise en charge</h3><div onclick="document.getElementById('modal-traiter-pec').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)">✕</div></div>
    <form method="POST" style="padding:24px"><input type="hidden" name="action" value="update_pec">
    <input type="hidden" name="pec_id" id="pec-id"><?= csrf_field() ?>
    <div class="form-group"><label>Statut *</label><select name="statut" required>
      <option value="accorde_total">Accorde total</option><option value="accorde_partiel">Accorde partiel</option><option value="refuse">Refuse</option></select></div>
    <div class="form-group"><label>N° Accord</label><input type="text" name="numero_accord"></div>
    <div class="form-group"><label>Montant accorde</label><input type="number" name="montant_accorde" id="pec-mt" step="0.01"></div>
    <div class="form-group"><label>Motif (si refuse)</label><textarea name="motif" rows="2"></textarea></div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
      <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-traiter-pec').style.display='none'">Annuler</button>
      <button type="submit" class="btn btn-green">Valider</button></div></form></div></div>
<script>function openUpdatePec(id,mt){document.getElementById('pec-id').value=id;document.getElementById('pec-mt').placeholder='Montant demande: '+mt;document.getElementById('modal-traiter-pec').style.display='flex';}</script>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
