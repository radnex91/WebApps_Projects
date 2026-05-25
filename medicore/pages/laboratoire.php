<?php
$currentPage = 'laboratoire';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

//  PRESCRIRE ANALYSE
if (can('analyses.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'prescrire') {
    csrf_verify();
    $v = (new Validator())->required('type_examen','Type d\'examen');
    $patient_id = post_int('patient_id');
    $prescr_id  = post_int('prescripteur_id') ?: (int)($_SESSION['user_id']??1);
    if (!$patient_id) { $flash=['red','Patient obligatoire.']; }
    elseif (!$v->passes()) { $flash=['red',$v->first_error()]; }
    else {
        $num = 'L-'.date('Y').'-'.str_pad((int)db_scalar("SELECT COUNT(*)+1 FROM analyses"),4,'0',STR_PAD_LEFT);
        db_exec("INSERT INTO analyses (numero,patient_id,prescripteur_id,type_examen,statut) VALUES (?,?,?,?,'prescrit')",
            [$num,$patient_id,$prescr_id,$v->get('type_examen')]);
        logActivity("Analyse $num prescrite", 'blue', 'analyse');
        $flash=['green',"Analyse prescrite avec succès."];
    }
}

//  METTRE  JOUR STATUT
if (can('analyses.update_resultat') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'update_statut') {
    csrf_verify();
    $id     = post_int('analyse_id');
    $statut = in_whitelist(post_str('statut'),['prescrit','en_cours','disponible','archive'],'prescrit');
    $result = post_str('resultat');
    assert_owns('analyses', $id);
    $date_res = in_array($statut,['disponible','archive']) ? 'NOW()' : 'NULL';
    db_exec("UPDATE analyses SET statut=?, resultat=?, date_resultat=IF(statut!='disponible' AND ?='disponible',NOW(),date_resultat) WHERE id=?",
        [$statut,$result,$statut,$id]);
    if ($statut === 'disponible') {
        db_exec("UPDATE analyses SET date_resultat=NOW() WHERE id=? AND date_resultat IS NULL",[$id]);
    }
    logActivity("Analyse #$id -> $statut", 'green', 'analyse', $id);
    header('Location: '.APP_URL.'/laboratoire.php?ok=1'); exit;
}

//  DONNÉES

//  EXPORT CSV ANALYSES 
if (get_str('export') === 'csv' && can('analyses.view')) {
    if (ob_get_level() > 0) ob_end_clean();
    $all = db_select(
        "SELECT a.numero, CONCAT(p.prenom,' ',p.nom) AS patient_nom, p.numero AS patient_num,
                a.type_examen, CONCAT(u.prenom,' ',u.nom) AS prescripteur,
                a.statut, a.date_creation, a.date_resultat, a.resultat
         FROM analyses a
         JOIN patients p ON p.id=a.patient_id
         JOIN utilisateurs u ON u.id=a.prescripteur_id
         ORDER BY a.date_creation DESC"
    );
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="analyses_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['N° Analyse','Patient','N° Dossier','Type examen','Prescripteur','Statut','Date prescription','Date resultat','Resultat'], ';');
    foreach ($all as $row) {
        fputcsv($out, [
            $row['numero'], $row['patient_nom'], $row['patient_num'],
            $row['type_examen'], $row['prescripteur'], $row['statut'],
            $row['date_creation'] ? fmt_date($row['date_creation']) : '',
            $row['date_resultat'] ? fmt_date($row['date_resultat']) : '',
            $row['resultat'] ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('laboratoire');

$filtre = in_whitelist(get_str('filtre'),['','prescrit','en_cours','disponible','archive'],'');
$search = get_str('search');
$where  = 'WHERE 1=1';
$params = [];
if ($filtre) { $where.=' AND a.statut=?'; $params[]=$filtre; }
if ($search) { $like="%$search%"; $where.=' AND (CONCAT(p.prenom," ",p.nom) LIKE ? OR a.numero LIKE ? OR a.type_examen LIKE ?)'; $params=array_merge($params,[$like,$like,$like]); }

$analyses = db_select("SELECT a.*,
    CONCAT(p.prenom,' ',p.nom) AS patient_nom, p.numero AS patient_num,
    CONCAT(u.prenom,' ',u.nom) AS prescripteur_nom
    FROM analyses a
    JOIN patients p ON p.id=a.patient_id
    JOIN utilisateurs u ON u.id=a.prescripteur_id
    $where ORDER BY a.date_creation DESC LIMIT 100", $params);

$patients  = db_select("SELECT id,CONCAT(prenom,' ',nom) AS nom_complet,numero FROM patients ORDER BY nom");
$medecins  = db_select("SELECT id,CONCAT(prenom,' ',nom) AS nom_complet FROM utilisateurs WHERE role='medecin' AND statut='actif' ORDER BY nom");

$stats = [
    'total'     => (int)db_scalar("SELECT COUNT(*) FROM analyses"),
    'prescrit'  => (int)db_scalar("SELECT COUNT(*) FROM analyses WHERE statut='prescrit'"),
    'en_cours'  => (int)db_scalar("SELECT COUNT(*) FROM analyses WHERE statut='en_cours'"),
    'dispo'     => (int)db_scalar("SELECT COUNT(*) FROM analyses WHERE statut='disponible'"),
];

$statutBadge = ['prescrit'=>'badge-blue','en_cours'=>'badge-yellow','disponible'=>'badge-green','archive'=>'badge-gray'];
$statutLabel = ['prescrit'=>'Prescrit','en_cours'=>'🔬 En cours','disponible'=>'✅ Disponible','archive'=>'Archivé'];
?>

<?php if (isset($_GET['ok'])): ?><div class="alert alert-green alert-auto"> Mis à jour.</div><?php endif; ?>
<?php if (!empty($flash)): ?><div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= $flash[0]==='green'?'':'' ?> <?= h($flash[1]) ?></div><?php endif; ?>

<div class="page-header-row">
  <div><h2> Laboratoire</h2><p><?= $stats['total'] ?> analyses  <?= $stats['en_cours'] ?> en cours  <?= $stats['dispo'] ?> résultats disponibles</p></div>
  <?php if (can('analyses.create')): ?><a href="laboratoire.php?export=csv" class="btn btn-ghost"> Exporter CSV</a>
  <?php if (can('analyses.create')): ?>
  <button class="btn btn-blue" onclick="document.getElementById('modal-labo').style.display='flex'">+ Prescrire une analyse</button>
  <?php endif; ?><?php endif; ?>
</div>

<div class="stats-grid mb-24">
  <div class="stat-card blue"><div class="stat-icon blue">📋</div><div class="stat-value"><?= $stats['prescrit'] ?></div><div class="stat-label">Prescrites</div></div>
  <div class="stat-card yellow"><div class="stat-icon yellow">🔬</div><div class="stat-value"><?= $stats['en_cours'] ?></div><div class="stat-label">En cours d'analyse</div></div>
  <div class="stat-card green"><div class="stat-icon green">✅</div><div class="stat-value"><?= $stats['dispo'] ?></div><div class="stat-label">Résultats disponibles</div></div>
  <div class="stat-card blue"><div class="stat-icon blue">📊</div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total analyses</div></div>
</div>

<!-- Filtres -->
<div style="display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap">
  <form method="GET" style="display:flex;gap:6px;flex-wrap:wrap;flex:1">
    <input type="text" name="search" value="<?= h($search) ?>" placeholder=" N° analyse, patient, type..."
      style="flex:1;min-width:180px;padding:8px 12px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text);font-size:13px;outline:none">
    <?php foreach ([''=>'Toutes','prescrit'=>'Prescrites','en_cours'=>'🔬 En cours','disponible'=>'Disponibles','archive'=>'Archivéées'] as $val=>$lbl): ?>
    <label style="display:flex;align-items:center;gap:4px;padding:7px 12px;background:var(--surface);border:1px solid <?= $filtre===$val?'var(--accent)':'var(--border)' ?>;border-radius:8px;cursor:pointer;font-size:12px;color:<?= $filtre===$val?'var(--accent2)':'var(--text2)' ?>">
      <input type="radio" name="filtre" value="<?= $val ?>" <?= $filtre===$val?'checked':'' ?> onchange="this.form.submit()" style="display:none"><?= $lbl ?>
    </label>
    <?php endforeach; ?>
    <?php if ($search): ?><a href="laboratoire.php" class="btn btn-ghost btn-sm">✕</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-header"><h3>Analyses</h3><span style="font-size:12px;color:var(--text2)"><?= count($analyses) ?> résultats</span></div>
  <table>
    <thead><tr><th>N° Analyse</th><th>Patient</th><th>Type d'examen</th><th>Prescripteur</th><th>Prélevé le</th><th>Résultat le</th><th>Statut</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($analyses as $a): ?>
    <tr>
      <td><strong><?= h($a['numero']) ?></strong></td>
      <td><?= h($a['patient_nom']) ?><br><span class="text-xs text3"><?= h($a['patient_num']) ?></span></td>
      <td><?= h($a['type_examen']) ?></td>
      <td style="font-size:12px"><?= h($a['prescripteur_nom']) ?></td>
      <td style="font-size:12px"><?= $a['date_prelevement']?fmt_date($a['date_prelevement'],true):'' ?></td>
      <td style="font-size:12px"><?= $a['date_resultat']?fmt_date($a['date_resultat'],true):'' ?></td>
      <td><span class="badge <?= $statutBadge[$a['statut']]??'badge-gray' ?>"><?= $statutLabel[$a['statut']]??$a['statut'] ?></span></td>
      <td>
        <button class="btn btn-sm btn-ghost"
          onclick="openResultModal(<?= (int)$a['id'] ?>,'<?= h(addslashes($a['type_examen'])) ?>','<?= h(addslashes($a['statut'])) ?>',`<?= addslashes(h($a['resultat']??'')) ?>`)">
          <?= $a['statut']==='disponible'?' Voir':' Saisir' ?>
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($analyses)): ?><tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text3)">Aucune analyse trouvée</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- MODAL PRESCRIRE -->
<div id="modal-labo" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:500px;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3> Prescrire une analyse</h3>
      <div onclick="document.getElementById('modal-labo').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)"></div>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="prescrire"><?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group form-full"><label>Patient *</label>
          <select name="patient_id" required style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value=""> Sélectionner </option>
            <?php foreach ($patients as $p): ?><option value="<?= (int)$p['id'] ?>"><?= h($p['nom_complet']) ?> (<?= h($p['numero']) ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group form-full"><label>Type d'examen *</label>
          <input type="text" name="type_examen" required maxlength="200" list="examens-list" placeholder="ex: Bilan sanguin complet, IRM cérébrale...">
          <datalist id="examens-list">
            <option>Bilan sanguin complet</option><option>NFS - Numration Formule Sanguine</option>
            <option>Bilan hépatique</option><option>Bilan rénal (cratinine, ure)</option>
            <option>ECG - Électrocardiogramme</option><option>Holter ECG 24h</option>
            <option>IRM cérébrale</option><option>Scanner thoracique</option>
            <option>Radio thorax</option><option>Échographie abdominale</option>
            <option>Spirométrie</option><option>Troponine + BNP</option>
            <option>Glycémie à jeun</option><option>HbA1c</option>
          </datalist>
        </div>
        <div class="form-group form-full"><label>Prescripteur</label>
          <select name="prescripteur_id" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <?php foreach ($medecins as $m): ?><option value="<?= (int)$m['id'] ?>"><?= h($m['nom_complet']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-labo').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue"> Prescrire</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL RSULTAT -->
<div id="modal-result" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:540px;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3 id="result-title">Résultat</h3>
      <div onclick="document.getElementById('modal-result').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)"></div>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="update_statut">
      <input type="hidden" name="analyse_id" id="result-id">
      <?= csrf_field() ?>
      <div class="form-group" style="margin-bottom:14px"><label>Statut</label>
        <select name="statut" id="result-statut" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
          <option value="prescrit">Prescrit</option>
          <option value="en_cours">🔬 En cours</option>
          <option value="disponible">✅ Disponible</option>
          <option value="archive">Archivé</option>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:20px"><label>Résultat / Observations</label>
        <textarea name="resultat" id="result-text" rows="5" style="width:100%;padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;resize:vertical" placeholder="Saisir les résultats de l'analyse..."></textarea>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-result').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">💾 Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
function openResultModal(id, type, statut, resultat) {
  document.getElementById('result-id').value    = id;
  document.getElementById('result-title').textContent = 'Analyse : ' + type;
  document.getElementById('result-statut').value = statut;
  document.getElementById('result-text').value   = resultat;
  document.getElementById('modal-result').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php';
