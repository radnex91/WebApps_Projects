<?php
$currentPage = 'imagerie';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$flash = null;

// --- POST : Prescrire un examen d'imagerie ---
if (can('imagerie.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'prescrire') {
    csrf_verify();
    $patient_id      = post_int('patient_id');
    $prescripteur_id = currentUser()['id'];
    $type_examen     = in_whitelist(post_str('type_examen'), ['radio_standard','scanner','irm','echographie','mammographie','angiographie','scintigraphie','autre'], 'radio_standard');
    $zone            = post_str('zone');
    $urgence         = in_whitelist(post_str('urgence'), ['normal','urgent','tres_urgent'], 'normal');
    $motif           = post_str('motif');

    // Generer numero
    $n = (int)db_scalar("SELECT COUNT(*) FROM examens_imagerie WHERE DATE(date_creation)=CURDATE()");
    $numero = 'IMG-'.date('Y').'-'.str_pad($n+1, 4, '0', STR_PAD_LEFT);

    $v = (new Validator())->required($patient_id>0, 'patient', 'Patient requis.')
        ->required($motif, 'motif', 'Motif requis.');
    if (!$v->passes()) { $flash = ['red', $v->first_error()]; }
    else {
        db_exec("INSERT INTO examens_imagerie (numero, patient_id, prescripteur_id, type_examen, zone_anatomique, urgence, motif) VALUES (?,?,?,?,?,?,?)",
            [$numero, $patient_id, $prescripteur_id, $type_examen, $zone, $urgence, $motif]);
        logActivity("Imagerie prescrite : $numero", 'blue', 'imagerie');
        $flash = ['green', "Examen $numero prescrit."];
    }
}

// --- POST : Saisir resultat imagerie ---
if (can('imagerie.update_resultat') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'resultat') {
    csrf_verify();
    $id       = get_signed_id('id', 'examens_imagerie');
    $resultat = post_str('resultat');
    $statut   = in_whitelist(post_str('statut'), ['realise','interprete','disponible'], 'disponible');

    db_exec("UPDATE examens_imagerie SET resultat=?, statut=?, date_realisation=IF(date_realisation IS NULL,NOW(),date_realisation) WHERE id=?",
        [$resultat, $statut, $id]);
    logActivity("Resultat imagerie #$id", 'green', 'imagerie', $id);
    $flash = ['green', 'Resultat enregistre.'];
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('imagerie');

// --- Donnees ---
$filtre_statut = in_whitelist(get_str('filtre'), ['','prescrit','planifie','realise','disponible','archive'], '');
$search = get_str('search');

$where = "1=1";
$params = [];
if ($filtre_statut) { $where .= " AND e.statut=?"; $params[] = $filtre_statut; }
if ($search) {
    $like = "%$search%";
    $where .= " AND (CONCAT(p.prenom,' ',p.nom) LIKE ? OR e.numero LIKE ?)";
    $params[] = $like; $params[] = $like;
}

$examens = db_select("SELECT e.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom, p.numero AS patient_num,
    CONCAT(u.prenom,' ',u.nom) AS prescripteur_nom
    FROM examens_imagerie e
    JOIN patients p ON p.id=e.patient_id
    JOIN utilisateurs u ON u.id=e.prescripteur_id
    WHERE $where ORDER BY FIELD(e.urgence,'tres_urgent','urgent','normal'), FIELD(e.statut,'prescrit','planifie','realise','interprete','disponible','archive'), e.date_creation DESC LIMIT 100", $params);

$stats = [
    'total'      => (int)db_scalar("SELECT COUNT(*) FROM examens_imagerie WHERE statut!='archive'"),
    'en_attente' => (int)db_scalar("SELECT COUNT(*) FROM examens_imagerie WHERE statut IN ('prescrit','planifie')"),
    'disponible' => (int)db_scalar("SELECT COUNT(*) FROM examens_imagerie WHERE statut='disponible'"),
    'urgents'    => (int)db_scalar("SELECT COUNT(*) FROM examens_imagerie WHERE urgence IN ('urgent','tres_urgent') AND statut IN ('prescrit','planifie')"),
];

$typeLabels = ['radio_standard'=>'Radio standard','scanner'=>'Scanner / CT','irm'=>'IRM','echographie'=>'Echographie','mammographie'=>'Mammographie','angiographie'=>'Angiographie','scintigraphie'=>'Scintigraphie','autre'=>'Autre'];
$typeIcon   = ['radio_standard'=>'🦴','scanner'=>'🖥️','irm'=>'🧲','echographie'=>'🔊','mammographie'=>'🎀','angiographie'=>'🫀','scintigraphie'=>'☢️','autre'=>'📌'];
$statBadge  = ['prescrit'=>'badge-yellow','planifie'=>'badge-blue','realise'=>'badge-yellow','interprete'=>'badge-blue','disponible'=>'badge-green','archive'=>'badge-gray'];
$statLabel  = ['prescrit'=>'Prescrit','planifie'=>'Planifie','realise'=>'Realise','interprete'=>'Interprete','disponible'=>'Disponible','archive'=>'Archive'];
$urgLabel   = ['normal'=>'','urgent'=>'⚠️ Urgent','tres_urgent'=>'🚨 Tres urgent'];
?>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= h($flash[1]) ?></div>
<?php endif; ?>

<div class="page-header-row">
  <div><h2>🩻 Imagerie medicale</h2><p><?= $stats['en_attente'] ?> en attente · <?= $stats['disponible'] ?> dispo. · <?= $stats['urgents'] ?> urgents</p></div>
  <?php if (can('imagerie.create')): ?>
  <button class="btn btn-blue" onclick="document.getElementById('modal-imagerie').style.display='flex'">+ Nouvel examen</button>
  <?php endif; ?>
</div>

<div class="stats-grid mb-24">
  <div class="stat-card yellow"><div class="stat-icon yellow">⏳</div><div class="stat-value"><?= $stats['en_attente'] ?></div><div class="stat-label">En attente</div></div>
  <div class="stat-card green"><div class="stat-icon green">✅</div><div class="stat-value"><?= $stats['disponible'] ?></div><div class="stat-label">Disponibles</div></div>
  <div class="stat-card red"><div class="stat-icon red">🚨</div><div class="stat-value"><?= $stats['urgents'] ?></div><div class="stat-label">Urgents</div></div>
  <div class="stat-card blue"><div class="stat-icon blue">📊</div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total actif</div></div>
</div>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
  <form method="GET" style="display:flex;gap:8px"><input type="search" name="search" placeholder="Rechercher..." value="<?= h($search) ?>" style="max-width:200px"><button class="btn btn-sm btn-blue">🔍</button></form>
  <?php foreach (['prescrit'=>'Prescrits','disponible'=>'Disponibles','archive'=>'Archives'] as $v=>$l): ?>
  <a href="?filtre=<?= $v ?>" class="badge <?= $filtre_statut==$v?'badge-blue':'badge-gray' ?>" style="text-decoration:none"><?= $l ?></a>
  <?php endforeach; ?>
  <?php if ($filtre_statut || $search): ?><a href="imagerie.php" class="badge badge-red" style="text-decoration:none">✕ Reset</a><?php endif; ?>
</div>

<div class="card">
  <table>
    <thead><tr><th>N°</th><th>Date</th><th>Patient</th><th>Type</th><th>Zone</th><th>Urgence</th><th>Prescrit par</th><th>Statut</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($examens as $e): ?>
      <tr style="<?= $e['urgence']!=='normal'?'background:rgba(255,100,100,0.05)':'' ?>">
        <td><strong><?= h($e['numero']) ?></strong></td>
        <td style="font-size:12px"><?= fmt_date($e['date_creation'], true) ?></td>
        <td><?= h($e['patient_nom']) ?><div style="font-size:10px;color:var(--text3)"><?= h($e['patient_num']) ?></div></td>
        <td><?= $typeIcon[$e['type_examen']]??'' ?> <?= $typeLabels[$e['type_examen']]??h($e['type_examen']) ?></td>
        <td><?= h($e['zone_anatomique'] ?? '-') ?></td>
        <td><?= $urgLabel[$e['urgence']] ?></td>
        <td style="font-size:12px"><?= h($e['prescripteur_nom']) ?></td>
        <td><span class="badge <?= $statBadge[$e['statut']]??'badge-gray' ?>"><?= $statLabel[$e['statut']]??$e['statut'] ?></span></td>
        <td>
          <?php if (can('imagerie.update_resultat') && in_array($e['statut'],['prescrit','planifie','realise'])): ?>
          <button class="btn btn-sm btn-blue" onclick="openResultat(<?= (int)$e['id'] ?>,'<?= h($e['numero']) ?>','<?= $typeLabels[$e['type_examen']]??$e['type_examen'] ?>','<?= h(addslashes($e['resultat']??'')) ?>')">Saisir resultat</button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($examens)): ?>
      <tr><td colspan="9" style="text-align:center;padding:48px;color:var(--text3)">Aucun examen d'imagerie.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Modal prescrire -->
<?php if (can('imagerie.create')): ?>
<div id="modal-imagerie" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:540px;max-width:95vw;box-shadow:0 24px 60px rgba(0,0,0,.7);max-height:90vh;overflow-y:auto">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>Prescrire un examen d'imagerie</h3>
      <div onclick="document.getElementById('modal-imagerie').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)">✕</div>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="prescrire">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group form-full"><label>Patient *</label>
          <select name="patient_id" required><option value="">-- --</option>
          <?php $patients = db_select("SELECT id, CONCAT(prenom,' ',nom) AS n, numero FROM patients ORDER BY nom LIMIT 100");
          foreach ($patients as $p): ?><option value="<?= (int)$p['id'] ?>"><?= h($p['n'].' - '.$p['numero']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="form-group"><label>Type d'examen *</label><select name="type_examen"><?php foreach ($typeLabels as $tk=>$tl): ?><option value="<?= $tk ?>"><?= $typeIcon[$tk] ?> <?= $tl ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Urgence</label><select name="urgence"><option value="normal">Normal</option><option value="urgent">Urgent</option><option value="tres_urgent">Tres urgent</option></select></div>
        <div class="form-group form-full"><label>Zone anatomique</label><input type="text" name="zone" placeholder="Ex: Thorax, Crane, Abdomen..."></div>
        <div class="form-group form-full"><label>Motif *</label><textarea name="motif" rows="2" required placeholder="Indication medicale..."></textarea></div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-imagerie').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">Prescrire</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Modal resultat -->
<?php if (can('imagerie.update_resultat')): ?>
<div id="modal-resultat" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:550px;max-width:95vw;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>Resultat imagerie</h3>
      <div onclick="document.getElementById('modal-resultat').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)">✕</div>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="resultat">
      <input type="hidden" name="id" id="res-id" value=""><?= csrf_field() ?>
      <div id="res-info" style="color:var(--text2);margin-bottom:12px;font-size:13px"></div>
      <div class="form-group"><label>Statut</label><select name="statut"><option value="realise">Realise</option><option value="disponible" selected>Disponible</option></select></div>
      <div class="form-group"><label>Compte-rendu / Resultat *</label><textarea name="resultat" id="res-text" rows="5" required placeholder="Rediger le compte-rendu..."></textarea></div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-resultat').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<script>
function openResultat(id, num, type, resultat){
  document.getElementById('res-id').value=id;
  document.getElementById('res-info').innerHTML='<strong>'+type+'</strong> - '+num;
  document.getElementById('res-text').value=resultat;
  document.getElementById('modal-resultat').style.display='flex';
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
