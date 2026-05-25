<?php
$currentPage = 'observations';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$flash = null;

// --- POST : Saisir une observation ---
if (can('observations.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'saisir') {
    csrf_verify();
    $patient_id = post_int('patient_id');
    $hosp_id    = post_int('hospitalisation_id');
    $type       = in_whitelist(post_str('type_observation'), [
        'temperature','ta_systolique','ta_diastolique','pouls','spo2','glycemie',
        'douleur_eva','freq_respiratoire','poids','taille','bmi','autres'
    ], 'temperature');
    $valeur     = post_float('valeur');
    $unite      = post_str('unite');
    $notes      = post_str('notes');

    $v = (new Validator())->required($type, 'type', 'Le type est requis.')
        ->required($patient_id > 0, 'patient', 'Patient requis.');
    if (!$v->passes()) { $flash = ['red', $v->first_error()]; }
    else {
        $unites_map = [
            'temperature' => '°C', 'ta_systolique' => 'mmHg', 'ta_diastolique' => 'mmHg',
            'pouls' => 'bpm', 'spo2' => '%', 'glycemie' => 'mg/dL', 'douleur_eva' => '/10',
            'freq_respiratoire' => '/min', 'poids' => 'kg', 'taille' => 'cm', 'bmi' => 'kg/m²', 'autres' => ''
        ];
        if (empty($unite)) $unite = $unites_map[$type] ?? '';

        db_exec("INSERT INTO observations_infirmieres (patient_id, hospitalisation_id, utilisateur_id, type_observation, valeur, unite, notes) VALUES (?,?,?,?,?,?,?)",
            [$patient_id, $hosp_id > 0 ? $hosp_id : null, currentUser()['id'], $type, $valeur, $unite, $notes]);
        logActivity("Saisie observation : ".htmlspecialchars($type)." = $valeur $unite", 'blue', 'observation');
        $flash = ['green', 'Observation enregistrée avec succès.'];
    }
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('observations');

// --- Filtres ---
$patient_id    = get_int('patient_id');
$filtre_type   = in_whitelist(get_str('filtre'), [
    '','temperature','ta_systolique','pouls','spo2','glycemie','douleur_eva','freq_respiratoire','poids','taille'
], '');
$search        = get_str('search');

// --- Patient sélectionné ---
$patientSelect = null;
if ($patient_id > 0) {
    $patientSelect = db_row("SELECT * FROM patients WHERE id=?", [$patient_id]);
}

// --- Liste patients pour selecteur (si pas de patient selectionné) ---
$patientsRecents = db_select("SELECT p.id, p.numero, p.nom, p.prenom, p.date_naissance, p.sexe,
    (SELECT COUNT(*) FROM hospitalisations WHERE patient_id=p.id AND statut='en_cours') AS hosp_active
    FROM patients p ORDER BY p.date_creation DESC LIMIT 30");

// --- Charger les observations si un patient est sélectionné ---
$observations = [];
if ($patient_id > 0) {
    $where  = "o.patient_id=?";
    $params = [$patient_id];
    if ($filtre_type) { $where .= " AND o.type_observation=?"; $params[] = $filtre_type; }
    $observations = db_select("SELECT o.*, CONCAT(u.prenom,' ',u.nom) AS saisi_par
        FROM observations_infirmieres o
        JOIN utilisateurs u ON u.id=o.utilisateur_id
        WHERE $where ORDER BY o.date_observation DESC LIMIT 200", $params);
}

// --- Hospitalisation active du patient ---
$hospActive = null;
if ($patient_id > 0) {
    $hospActive = db_row("SELECT h.*, d.nom AS dept_nom, l.numero AS lit_numero
        FROM hospitalisations h
        JOIN lits l ON l.id=h.lit_id
        JOIN departements d ON d.id=h.departement_id
        WHERE h.patient_id=? AND h.statut='en_cours'", [$patient_id]);
}

// --- Stats rapides ---
$nbObs = $patient_id > 0 ? (int)db_scalar("SELECT COUNT(*) FROM observations_infirmieres WHERE patient_id=?", [$patient_id]) : 0;

// --- Données pour le graphique (dernières 48h du type filtré) ---
$graphData = [];
if ($patient_id > 0 && $filtre_type) {
    $graphData = db_select("SELECT valeur, date_observation FROM observations_infirmieres
        WHERE patient_id=? AND type_observation=? AND date_observation >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ORDER BY date_observation ASC", [$patient_id, $filtre_type]);
}

// --- Alertes valeurs anormales ---
$alertes = [];
if ($patient_id > 0) {
    $dernieres = db_select("SELECT o1.* FROM observations_infirmieres o1
        INNER JOIN (SELECT type_observation, MAX(date_observation) AS max_date FROM observations_infirmieres WHERE patient_id=? GROUP BY type_observation) o2
        ON o1.type_observation=o2.type_observation AND o1.date_observation=o2.max_date
        WHERE o1.patient_id=?", [$patient_id, $patient_id]);
    $seuils = [
        'temperature'     => ['min'=>36.0, 'max'=>38.0],
        'ta_systolique'   => ['min'=>90,  'max'=>140],
        'ta_diastolique'  => ['min'=>60,  'max'=>90],
        'pouls'           => ['min'=>60,  'max'=>100],
        'spo2'            => ['min'=>95,  'max'=>100],
        'glycemie'        => ['min'=>70,  'max'=>110],
        'freq_respiratoire'=>['min'=>12,  'max'=>20],
    ];
    foreach ($dernieres as $obs) {
        if (isset($seuils[$obs['type_observation']])) {
            $s = $seuils[$obs['type_observation']];
            if ($obs['valeur'] < $s['min'] || $obs['valeur'] > $s['max']) {
                $label = ['temperature'=>'Température','ta_systolique'=>'TA Systolique','ta_diastolique'=>'TA Diastolique',
                    'pouls'=>'Pouls','spo2'=>'SpO2','glycemie'=>'Glycémie','freq_respiratoire'=>'Freq. Resp.'][$obs['type_observation']] ?? $obs['type_observation'];
                $alertes[] = ['label'=>$label, 'valeur'=>$obs['valeur'], 'unite'=>$obs['unite'],
                    'min'=>$s['min'], 'max'=>$s['max'], 'date'=>$obs['date_observation']];
            }
        }
    }
}

// --- Labels des types ---
$typeLabels = [
    'temperature' => 'Température', 'ta_systolique' => 'TA Systolique', 'ta_diastolique' => 'TA Diastolique',
    'pouls' => 'Pouls', 'spo2' => 'SpO₂', 'glycemie' => 'Glycémie',
    'douleur_eva' => 'Douleur (EVA)', 'freq_respiratoire' => 'Freq. Respiratoire',
    'poids' => 'Poids', 'taille' => 'Taille', 'bmi' => 'IMC', 'autres' => 'Autres'
];

$typeIcon = [
    'temperature' => '🌡️', 'ta_systolique' => '❤️', 'pouls' => '💓', 'spo2' => '🫁',
    'glycemie' => '🩸', 'douleur_eva' => '😣', 'freq_respiratoire' => '🫁',
    'poids' => '⚖️', 'taille' => '📏', 'bmi' => '📐', 'autres' => '📌'
];
?>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= h($flash[1]) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h3>🌡️ Signes vitaux &amp; Observations infirmières</h3>
    <span style="font-size:12px;color:var(--text2)"><?= $patient_id > 0 ? $nbObs.' observation(s)' : 'Sélectionnez un patient' ?></span>
  </div>

  <!-- Barre de recherche patient -->
  <form method="GET" style="padding:16px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="page" value="observations">
    <input type="search" name="search" placeholder="Rechercher un patient..." value="<?= h($search) ?>" style="max-width:300px">
    <button type="submit" class="btn btn-sm btn-blue">🔍 Rechercher</button>
    <?php if ($patient_id > 0): ?>
    <a href="observations.php" class="btn btn-sm btn-ghost">✕ Réinitialiser</a>
    <?php endif; ?>
  </form>

  <?php if ($search && !$patient_id): ?>
  <!-- Résultats de recherche -->
  <table>
    <thead><tr><th>Patient</th><th>N° Dossier</th><th>Âge</th><th>Hospitalisation</th><th></th></tr></thead>
    <tbody>
    <?php
    $like = "%$search%";
    $resultats = db_select("SELECT * FROM patients WHERE nom LIKE ? OR prenom LIKE ? OR numero LIKE ? LIMIT 20", [$like,$like,$like]);
    foreach ($resultats as $r):
        $age = !empty($r['date_naissance']) ? (int)((time()-strtotime($r['date_naissance']))/31536000) : '?';
        $hospEncours = (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE patient_id=? AND statut='en_cours'", [$r['id']]);
    ?>
      <tr>
        <td><strong><?= h($r['prenom'].' '.$r['nom']) ?></strong></td>
        <td><?= h($r['numero']) ?></td>
        <td><?= $age ?> ans</td>
        <td><?= $hospEncours > 0 ? '<span class="badge badge-green">Hospitalisé</span>' : '<span class="badge badge-gray">Non hospitalisé</span>' ?></td>
        <td><a href="observations.php?patient_id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-blue">Sélectionner</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($resultats)): ?>
      <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text3)">Aucun patient trouvé.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  <?php elseif (!$patient_id): ?>
  <!-- Liste patients récents -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;padding:16px">
    <?php foreach ($patientsRecents as $pr):
      $init = strtoupper(mb_substr($pr['prenom']??'',0,1).mb_substr($pr['nom']??'',0,1));
    ?>
    <a href="observations.php?patient_id=<?= (int)$pr['id'] ?>" style="display:flex;align-items:center;gap:12px;padding:12px;border-radius:10px;background:var(--bg);border:1px solid var(--border);text-decoration:none;transition:border-color 0.2s">
      <div style="width:42px;height:42px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px"><?= h($init) ?></div>
      <div>
        <div style="font-weight:600;color:var(--text)"><?= h($pr['prenom'].' '.$pr['nom']) ?></div>
        <div style="font-size:11px;color:var(--text3)"><?= h($pr['numero']) ?></div>
      </div>
      <?php if ($pr['hosp_active'] > 0): ?><span class="badge badge-green" style="margin-left:auto">Actif</span><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php if ($patient_id > 0 && $patientSelect): ?>
<!-- En-tête patient -->
<div class="card" style="border-left:4px solid var(--accent);margin-top:16px">
  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;padding:4px 0">
    <div style="width:52px;height:52px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px">
      <?= h(strtoupper(mb_substr($patientSelect['prenom']??'',0,1).mb_substr($patientSelect['nom']??'',0,1))) ?>
    </div>
    <div>
      <div style="font-size:18px;font-weight:700"><?= h($patientSelect['prenom'].' '.$patientSelect['nom']) ?></div>
      <div style="font-size:12px;color:var(--text2)"><?= h($patientSelect['numero']) ?> ·
        <?= !empty($patientSelect['date_naissance']) ? (int)((time()-strtotime($patientSelect['date_naissance']))/31536000).' ans' : 'Âge inconnu' ?> ·
        <?= h($patientSelect['sexe'] ?? '') ?>
      </div>
    </div>
    <div style="margin-left:auto;display:flex;gap:8px">
      <?php if ($hospActive): ?>
      <span class="badge badge-blue"><?= h($hospActive['dept_nom']) ?> - Lit <?= h($hospActive['lit_numero']) ?></span>
      <?php endif; ?>
      <button class="btn btn-blue" onclick="document.getElementById('modal-saisie').style.display='flex'">+ Nouvelle observation</button>
    </div>
  </div>
  <?php if (!empty($patientSelect['allergies'])): ?>
  <div style="margin-top:8px;padding:6px 10px;background:rgba(255,100,100,0.1);border-radius:6px;font-size:12px;color:var(--red)">⚠️ Allergies : <?= h($patientSelect['allergies']) ?></div>
  <?php endif; ?>
</div>

<!-- Alertes valeurs anormales -->
<?php if (!empty($alertes)): ?>
<div class="alert alert-red mb-24">
  <strong>⚠️ Valeurs hors normes détectées :</strong>
  <?php foreach ($alertes as $al): ?>
    <div style="font-size:12px;margin-top:4px">
      <?= h($al['label']) ?> : <strong><?= $al['valeur'] ?> <?= h($al['unite']) ?></strong>
      (norme : <?= $al['min'] ?> - <?= $al['max'] ?>)
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Filtres par type -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin:16px 0">
  <?php foreach (['temperature','ta_systolique','pouls','spo2','glycemie','douleur_eva','freq_respiratoire','poids','taille'] as $tk): ?>
  <a href="?patient_id=<?= $patient_id ?>&filtre=<?= $tk ?>" class="badge <?= $filtre_type===$tk ? 'badge-blue' : 'badge-gray' ?>" style="text-decoration:none">
    <?= $typeIcon[$tk]??'' ?> <?= $typeLabels[$tk] ?>
  </a>
  <?php endforeach; ?>
  <?php if ($filtre_type): ?>
  <a href="?patient_id=<?= $patient_id ?>" class="badge badge-red" style="text-decoration:none">✕ Tout voir</a>
  <?php endif; ?>
</div>

<!-- Graphique tendance (si filtre actif) -->
<?php if ($filtre_type && count($graphData) >= 2): ?>
<div class="card">
  <div class="card-header"><h3>📈 Tendance : <?= $typeLabels[$filtre_type] ?> (48h)</h3></div>
  <div style="padding:16px">
    <canvas id="chart-obs" style="width:100%;height:250px"></canvas>
  </div>
  <script>
  document.addEventListener('DOMContentLoaded',function(){
    var c=document.getElementById('chart-obs'),ctx=c.getContext('2d'),
        d=<?= json_encode(array_map(fn($r)=>[strtotime($r['date_observation'])*1000,(float)$r['valeur']],$graphData)) ?>,
        w=c.parentElement.clientWidth;c.width=w;c.height=250;
    var pad=40,w2=w-pad*2,h2=200,vals=d.map(function(p){return p[1];}),
        min=Math.min.apply(null,vals)*0.9,max=Math.max.apply(null,vals)*1.1,
        tmin=d[0][0],tmax=d[d.length-1][0],range=max-min||1,trange=tmax-tmin||1;
    // grille
    ctx.strokeStyle='rgba(255,255,255,0.08)';ctx.lineWidth=1;
    for(var i=0;i<=4;i++){var y=pad+i*h2/4;ctx.beginPath();ctx.moveTo(pad,y);ctx.lineTo(w-pad,y);ctx.stroke();}
    // courbe
    ctx.beginPath();ctx.strokeStyle='#3b82f6';ctx.lineWidth=2.5;
    d.forEach(function(p,i){
        var x=pad+(p[0]-tmin)/trange*w2,y=pad+(1-(p[1]-min)/range)*h2;
        i===0?ctx.moveTo(x,y):ctx.lineTo(x,y);
    });ctx.stroke();
    // points
    d.forEach(function(p,i){
        var x=pad+(p[0]-tmin)/trange*w2,y=pad+(1-(p[1]-min)/range)*h2;
        ctx.beginPath();ctx.fillStyle='#3b82f6';ctx.arc(x,y,3,0,Math.PI*2);ctx.fill();
    });
    // labels axe Y
    ctx.fillStyle='rgba(255,255,255,0.5)';ctx.font='10px sans-serif';ctx.textAlign='right';
    for(var i=0;i<=4;i++){var y=pad+i*h2/4,v=max-i*range/4;ctx.fillText(v.toFixed(1),pad-6,y+3);}
  });
  </script>
</div>
<?php endif; ?>

<!-- Tableau des observations -->
<div class="card">
  <div class="card-header">
    <h3><?= $filtre_type ? $typeLabels[$filtre_type] : 'Toutes les observations' ?></h3>
  </div>
  <table>
    <thead><tr><th>Date</th><th>Type</th><th>Valeur</th><th>Saisi par</th><th>Notes</th></tr></thead>
    <tbody>
    <?php foreach ($observations as $obs):
        $anormale = false;
        $seuils = ['temperature'=>[36,38],'ta_systolique'=>[90,140],'ta_diastolique'=>[60,90],'pouls'=>[60,100],'spo2'=>[95,100],'glycemie'=>[70,110],'freq_respiratoire'=>[12,20]];
        if(isset($seuils[$obs['type_observation']])){$s=$seuils[$obs['type_observation']];$anormale=$obs['valeur']<$s[0]||$obs['valeur']>$s[1];}
    ?>
      <tr style="<?= $anormale?'background:rgba(255,100,100,0.08);border-left:3px solid var(--red)':'' ?>">
        <td><?= fmt_date($obs['date_observation'], true) ?></td>
        <td><?= $typeIcon[$obs['type_observation']]??'📌' ?> <?= $typeLabels[$obs['type_observation']]??h($obs['type_observation']) ?></td>
        <td><strong><?= $obs['valeur'] ?> <?= h($obs['unite']) ?></strong> <?= $anormale?'⚠️':'' ?></td>
        <td><?= h($obs['saisi_par']) ?></td>
        <td style="max-width:200px;color:var(--text2)"><?= h(mb_strlen($obs['notes']??'')>80?mb_substr($obs['notes'],0,80).'...':$obs['notes']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($observations)): ?>
      <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text3)">
        Aucune observation enregistrée. Cliquez sur "Nouvelle observation" pour commencer.
      </td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Modal : Saisie observation -->
<?php if ($patient_id > 0 && can('observations.create')): ?>
<div id="modal-saisie" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:540px;max-width:95vw;box-shadow:0 24px 60px rgba(0,0,0,.7);max-height:90vh;overflow-y:auto">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>Nouvelle observation - <?= h($patientSelect['prenom'].' '.$patientSelect['nom']) ?></h3>
      <div onclick="document.getElementById('modal-saisie').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)">✕</div>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="saisir">
      <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
      <?php if ($hospActive): ?><input type="hidden" name="hospitalisation_id" value="<?= (int)$hospActive['id'] ?>"><?php endif; ?>
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group form-full">
          <label>Type d'observation *</label>
          <select name="type_observation" id="obs-type" onchange="updateObsFields()" required>
            <?php foreach ($typeLabels as $tk=>$tl): if ($tk==='ta_diastolique'||$tk==='bmi') continue; ?>
            <option value="<?= $tk ?>"><?= $typeIcon[$tk]??'' ?> <?= $tl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Valeur * <span id="obs-unite-hint" style="color:var(--text3)"></span></label>
          <input type="number" name="valeur" id="obs-valeur" step="0.1" required style="font-size:22px;font-weight:700">
        </div>
        <div class="form-group">
          <label>Unité</label>
          <input type="text" name="unite" id="obs-unite" placeholder="Auto">
        </div>
        <div class="form-group form-full">
          <label>Notes</label>
          <textarea name="notes" rows="2" placeholder="Précisions éventuelles..."></textarea>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-saisie').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<script>
function updateObsFields(){
    var u={'temperature':'°C','ta_systolique':'mmHg','pouls':'bpm','spo2':'%','glycemie':'mg/dL','douleur_eva':'/10','freq_respiratoire':'/min','poids':'kg','taille':'cm'};
    var t=document.getElementById('obs-type').value;
    document.getElementById('obs-unite-hint').textContent='('+(u[t]||'')+')';
    document.getElementById('obs-unite').placeholder=u[t]||'';
}
updateObsFields();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
