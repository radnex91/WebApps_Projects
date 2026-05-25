<?php
$currentPage = 'maternite';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$flash = null;

// --- POST : Creer un suivi de grossesse ---
if (can('maternite.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'creer_suivi') {
    csrf_verify();
    $patient_id = post_int('patient_id');
    $ddr        = post_str('date_dernieres_regles');
    $dpa        = post_str('date_prevue_accouchement');
    $type       = in_whitelist(post_str('type_grossesse'), ['simple','gemellaire','multiple'], 'simple');
    $risque     = in_whitelist(post_str('groupe_risque'), ['bas','moyen','haut'], 'bas');
    $notes      = post_str('notes');

    if ($patient_id > 0) {
        db_exec("INSERT INTO suivi_grossesses (patient_id, date_dernieres_regles, date_prevue_accouchement, type_grossesse, groupe_risque, notes) VALUES (?,?,?,?,?,?)",
            [$patient_id, $ddr?:null, $dpa?:null, $type, $risque, $notes]);
        logActivity("Suivi grossesse cree", 'blue', 'maternite');
        $flash = ['green', 'Suivi de grossesse cree.'];
    }
}

// --- POST : Enregistrer un accouchement ---
if (can('maternite.update') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'accouchement') {
    csrf_verify();
    $suivi_id    = post_int('suivi_id');
    $patient_id  = post_int('patient_id');
    $date_acc    = post_str('date_accouchement');
    $type_acc    = in_whitelist(post_str('type_acc'), ['voie_basse','cesarienne_programmee','cesarienne_urgence','assiste'], 'voie_basse');
    $presentation = in_whitelist(post_str('presentation'), ['cephalique','siege','transverse'], 'cephalique');
    $peri        = post_int('peridurale') ? 1 : 0;
    $episio      = post_int('episiotomie') ? 1 : 0;
    $complications = post_str('complications');
    // Nouveau-ne
    $nn_sexe     = in_whitelist(post_str('nn_sexe'), ['M','F','Autre'], 'M');
    $nn_poids    = post_int('nn_poids');
    $nn_taille   = post_int('nn_taille');
    $nn_apgar1   = post_int('nn_apgar1');
    $nn_apgar5   = post_int('nn_apgar5');
    $nn_statut   = in_whitelist(post_str('nn_statut'), ['vivant','decede','transfere'], 'vivant');
    $nn_comp     = post_str('nn_complications');

    if ($patient_id > 0 && $date_acc) {
        $acc_id = db_exec("INSERT INTO accouchements (suivi_grossesse_id, patient_id, sage_femme_id, date_accouchement, type_accouchement, presentation, peridurale, episiotomie, complications_mere) VALUES (?,?,?,?,?,?,?,?,?)",
            [$suivi_id>0?$suivi_id:null, $patient_id, currentUser()['id'], $date_acc, $type_acc, $presentation, $peri, $episio, $complications]);
        if ($nn_poids > 0) {
            db_exec("INSERT INTO nouveau_nes (accouchement_id, sexe, date_naissance, poids_grammes, taille_cm, apgar_1min, apgar_5min, statut, complications) VALUES (?,?,?,?,?,?,?,?,?)",
                [$acc_id, $nn_sexe, $date_acc, $nn_poids, $nn_taille>0?$nn_taille:null, $nn_apgar1>0?$nn_apgar1:null, $nn_apgar5>0?$nn_apgar5:null, $nn_statut, $nn_comp]);
        }
        // Mettre a jour statut suivi grossesse
        if ($suivi_id > 0) db_exec("UPDATE suivi_grossesses SET statut='accouchee' WHERE id=?", [$suivi_id]);
        logActivity("Accouchement enregistre - patient #$patient_id", 'green', 'maternite');
        $flash = ['green', 'Accouchement enregistre avec succes.'];
    }
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('maternite');

// --- Donnees ---
$filtre_statut = in_whitelist(get_str('filtre'), ['','en_cours','accouchee','complication','terminee'], '');

$where = "1=1";
$params = [];
if ($filtre_statut) { $where .= " AND sg.statut=?"; $params[] = $filtre_statut; }
else { $where .= " AND sg.statut='en_cours'"; }

$grossesses = db_select("SELECT sg.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom, p.numero AS patient_num, p.date_naissance
    FROM suivi_grossesses sg
    JOIN patients p ON p.id=sg.patient_id
    WHERE $where ORDER BY sg.date_creation DESC LIMIT 100", $params);

// Accouchements recents
$accouchements = db_select("SELECT acc.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom,
    nn.sexe AS nn_sexe, nn.poids_grammes, nn.apgar_1min, nn.apgar_5min, nn.statut AS nn_statut
    FROM accouchements acc
    JOIN patients p ON p.id=acc.patient_id
    LEFT JOIN nouveau_nes nn ON nn.accouchement_id=acc.id
    ORDER BY acc.date_accouchement DESC LIMIT 30");

$stats = [
    'en_cours'  => (int)db_scalar("SELECT COUNT(*) FROM suivi_grossesses WHERE statut='en_cours'"),
    'accouchees'=> (int)db_scalar("SELECT COUNT(*) FROM suivi_grossesses WHERE statut='accouchee' AND MONTH(date_creation)=MONTH(NOW())"),
    'haut_risque'=> (int)db_scalar("SELECT COUNT(*) FROM suivi_grossesses WHERE groupe_risque='haut' AND statut='en_cours'"),
    'acc_mois'  => (int)db_scalar("SELECT COUNT(*) FROM accouchements WHERE MONTH(date_accouchement)=MONTH(NOW())"),
];

$statGrossBadge = ['en_cours'=>'badge-blue','accouchee'=>'badge-green','complication'=>'badge-red','terminee'=>'badge-gray'];
$statGrossLabel = ['en_cours'=>'En cours','accouchee'=>'Accouchee','complication'=>'Complication','terminee'=>'Terminee'];
?>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= h($flash[1]) ?></div>
<?php endif; ?>

<div class="page-header-row">
  <div><h2>🤰 Maternite &amp; Obstetrique</h2><p><?= $stats['en_cours'] ?> suivis actifs · <?= $stats['haut_risque'] ?> haut risque · <?= $stats['acc_mois'] ?> acc.(mois)</p></div>
  <?php if (can('maternite.create')): ?>
  <button class="btn btn-blue" onclick="document.getElementById('modal-grossesse').style.display='flex'">+ Nouveau suivi</button>
  <?php endif; ?>
</div>

<div class="stats-grid mb-24">
  <div class="stat-card blue"><div class="stat-icon">🤰</div><div class="stat-value"><?= $stats['en_cours'] ?></div><div class="stat-label">Suivis actifs</div></div>
  <div class="stat-card red"><div class="stat-icon">⚠️</div><div class="stat-value"><?= $stats['haut_risque'] ?></div><div class="stat-label">Haut risque</div></div>
  <div class="stat-card green"><div class="stat-icon">👶</div><div class="stat-value"><?= $stats['acc_mois'] ?></div><div class="stat-label">Accouch. (mois)</div></div>
  <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-value"><?= $stats['accouchees'] ?></div><div class="stat-label">Accouchees (mois)</div></div>
</div>

<!-- Filtres -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
  <?php foreach (['en_cours'=>'En cours','accouchee'=>'Accouchees','complication'=>'Complications'] as $v=>$l): ?>
  <a href="?filtre=<?= $v ?>" class="badge <?= $filtre_statut==$v?'badge-blue':'badge-gray' ?>" style="text-decoration:none"><?= $l ?></a>
  <?php endforeach; ?>
  <?php if ($filtre_statut): ?><a href="maternite.php" class="badge badge-red" style="text-decoration:none">✕ Reset</a><?php endif; ?>
</div>

<!-- Suivis grossesse -->
<h3 style="margin-bottom:12px">Suivis de grossesse</h3>
<div class="card"><table>
  <thead><tr><th>Patiente</th><th>DDR</th><th>DPA</th><th>Type</th><th>Risque</th><th>CPN</th><th>Statut</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($grossesses as $g): ?>
    <tr style="<?= $g['groupe_risque']==='haut'?'background:rgba(255,100,100,0.05)':'' ?>">
      <td><strong><?= h($g['patient_nom']) ?></strong><div style="font-size:10px;color:var(--text3)"><?= h($g['patient_num']) ?></div></td>
      <td><?= $g['date_dernieres_regles'] ? fmt_date($g['date_dernieres_regles']) : '?' ?></td>
      <td><?= $g['date_prevue_accouchement'] ? '<strong>'.fmt_date($g['date_prevue_accouchement']).'</strong>' : '?' ?></td>
      <td><?= ucfirst($g['type_grossesse']) ?></td>
      <td><span class="badge <?= $g['groupe_risque']==='haut'?'badge-red':($g['groupe_risque']==='moyen'?'badge-yellow':'badge-green') ?>"><?= ucfirst($g['groupe_risque']) ?></span></td>
      <td><?= $g['consultations'] ?></td>
      <td><span class="badge <?= $statGrossBadge[$g['statut']] ?>"><?= $statGrossLabel[$g['statut']] ?></span></td>
      <td><?php if (can('maternite.update') && $g['statut']==='en_cours'): ?>
        <button class="btn btn-sm btn-green" onclick="openAccouchement(<?= (int)$g['id'] ?>,<?= (int)$g['patient_id'] ?>,'<?= h($g['patient_nom']) ?>')">Accouchement</button>
      <?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($grossesses)): ?><tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text3)">Aucun suivi.</td></tr><?php endif; ?>
</tbody></table></div>

<!-- Accouchements recents -->
<h3 style="margin:24px 0 12px">Accouchements recents</h3>
<div class="card"><table>
  <thead><tr><th>Date</th><th>Patiente</th><th>Type</th><th>Sexe NN</th><th>Poids (g)</th><th>APGAR 1/5</th><th>Statut NN</th></tr></thead>
  <tbody>
  <?php foreach ($accouchements as $a): ?>
    <tr><td><?= fmt_date($a['date_accouchement'], true) ?></td>
      <td><?= h($a['patient_nom']) ?></td>
      <td><?= h($a['type_accouchement']) ?><?= $a['peridurale']?' (+Peri)':'' ?><?= $a['episiotomie']?' (+Episio)':'' ?></td>
      <td><?= $a['nn_sexe'] ?? '-' ?></td>
      <td><strong><?= $a['poids_grammes'] ? $a['poids_grammes'].'g' : '-' ?></strong></td>
      <td><?= $a['apgar_1min']??'?' ?>/<?= $a['apgar_5min']??'?' ?></td>
      <td><span class="badge <?= $a['nn_statut']==='vivant'?'badge-green':'badge-red' ?>"><?= $a['nn_statut'] ?></span></td></tr>
  <?php endforeach; ?>
  <?php if (empty($accouchements)): ?><tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text3)">Aucun accouchement.</td></tr><?php endif; ?>
</tbody></table></div>

<!-- Modal nouveau suivi -->
<?php if (can('maternite.create')): ?>
<div id="modal-grossesse" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:500px;max-width:95vw;box-shadow:0 24px 60px rgba(0,0,0,.7);max-height:90vh;overflow-y:auto">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>Nouveau suivi de grossesse</h3><div onclick="document.getElementById('modal-grossesse').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)">✕</div></div>
    <form method="POST" style="padding:24px"><input type="hidden" name="action" value="creer_suivi"><?= csrf_field() ?>
    <div class="form-group"><label>Patiente *</label><select name="patient_id" required><option value="">-- --</option>
    <?php $patientes = db_select("SELECT id, CONCAT(prenom,' ',nom) AS n, numero FROM patients WHERE sexe='F' ORDER BY nom LIMIT 100");
    foreach ($patientes as $p): ?><option value="<?= (int)$p['id'] ?>"><?= h($p['n'].' - '.$p['numero']) ?></option><?php endforeach; ?></select></div>
    <div class="form-grid">
      <div class="form-group"><label>Date dernieres regles</label><input type="date" name="date_dernieres_regles"></div>
      <div class="form-group"><label>Date prevue accouch.</label><input type="date" name="date_prevue_accouchement"></div>
      <div class="form-group"><label>Type</label><select name="type_grossesse"><option value="simple">Simple</option><option value="gemellaire">Gemellaire</option><option value="multiple">Multiple</option></select></div>
      <div class="form-group"><label>Groupe de risque</label><select name="groupe_risque"><option value="bas">Bas</option><option value="moyen">Moyen</option><option value="haut">Haut</option></select></div>
      <div class="form-group form-full"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
      <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-grossesse').style.display='none'">Annuler</button>
      <button type="submit" class="btn btn-blue">Creer le suivi</button></div></form></div></div>
<?php endif; ?>

<!-- Modal enregistrer accouchement -->
<?php if (can('maternite.update')): ?>
<div id="modal-accouchement" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:680px;max-width:95vw;box-shadow:0 24px 60px rgba(0,0,0,.7);max-height:90vh;overflow-y:auto">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>Enregistrer un accouchement</h3><div onclick="document.getElementById('modal-accouchement').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)">✕</div></div>
    <form method="POST" style="padding:24px"><input type="hidden" name="action" value="accouchement">
    <input type="hidden" name="suivi_id" id="acc-suivi-id"><input type="hidden" name="patient_id" id="acc-patient-id"><?= csrf_field() ?>
    <div id="acc-patient-name" style="font-weight:600;margin-bottom:12px;color:var(--accent2)"></div>
    <div class="form-grid">
      <div class="form-group form-full"><label>Date et heure *</label><input type="datetime-local" name="date_accouchement" required></div>
      <div class="form-group"><label>Type *</label><select name="type_acc"><option value="voie_basse">Voie basse</option><option value="cesarienne_programmee">Cesarienne programmee</option><option value="cesarienne_urgence">Cesarienne urgence</option><option value="assiste">Assiste</option></select></div>
      <div class="form-group"><label>Presentation</label><select name="presentation"><option value="cephalique">Cephalique</option><option value="siege">Siege</option><option value="transverse">Transverse</option></select></div>
      <div class="form-group"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="peridurale" value="1"> Peridurale</label></div>
      <div class="form-group"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="episiotomie" value="1"> Episiotomie</label></div>
    </div>
    <div class="form-group"><label>Complications mere</label><textarea name="complications" rows="2"></textarea></div>
    <hr style="margin:16px 0;border-color:var(--border)">
    <h4 style="margin-bottom:12px">👶 Nouveau-ne</h4>
    <div class="form-grid">
      <div class="form-group"><label>Sexe</label><select name="nn_sexe"><option value="M">Garcon</option><option value="F">Fille</option><option value="Autre">Autre</option></select></div>
      <div class="form-group"><label>Poids (grammes)</label><input type="number" name="nn_poids" placeholder="3000" min="100" max="7000"></div>
      <div class="form-group"><label>Taille (cm)</label><input type="number" name="nn_taille" placeholder="50"></div>
      <div class="form-group"><label>Perim. cranien (cm)</label><input type="number" name="nn_pc"></div>
      <div class="form-group"><label>APGAR 1 min</label><input type="number" name="nn_apgar1" min="0" max="10"></div>
      <div class="form-group"><label>APGAR 5 min</label><input type="number" name="nn_apgar5" min="0" max="10"></div>
      <div class="form-group"><label>Statut NN</label><select name="nn_statut"><option value="vivant">Vivant</option><option value="decede">Decede</option><option value="transfere">Transfere</option></select></div>
      <div class="form-group form-full"><label>Complications NN</label><textarea name="nn_complications" rows="2"></textarea></div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
      <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-accouchement').style.display='none'">Annuler</button>
      <button type="submit" class="btn btn-green">Enregistrer l'accouchement</button></div></form></div></div>
<script>function openAccouchement(sid,pid,nom){document.getElementById('acc-suivi-id').value=sid;document.getElementById('acc-patient-id').value=pid;document.getElementById('acc-patient-name').textContent='Patiente : '+nom;document.getElementById('modal-accouchement').style.display='flex';}</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
