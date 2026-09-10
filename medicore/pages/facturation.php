<?php
$currentPage = 'facturation';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/comptabilite.php';
requireLogin();

//  CREER FACTURE 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('factures.create') && post_str('action') === 'create_facture') {
    csrf_verify();
    $patient_id = post_int('patient_id');
    $montant    = post_float('montant_total');
    $assurance  = post_float('montant_assurance');
    $patient_p  = round($montant - $assurance, 2);
    $type_ass   = post_str('assurance_type');
    $notes      = post_str('notes');
    if (!$patient_id || $montant <= 0) {
        $flash = ['red', 'Patient et montant obligatoires.'];
    } else {
        $num = 'FAC-' . date('Y') . '-' . str_pad((int)db_scalar("SELECT COUNT(*)+1 FROM factures"), 5, '0', STR_PAD_LEFT);
        $facture_id = db_exec(
            "INSERT INTO factures (numero,patient_id,montant_total,montant_assurance,montant_patient,assurance_type,statut,notes)
             VALUES (?,?,?,?,?,?,'en_attente',?)",
            [$num, $patient_id, $montant, $assurance, $patient_p, $type_ass, $notes]
        );
        //  Comptabilité : reconnaissance revenu + créances
        compta_on_facture([
            'id' => (int)$facture_id, 'numero' => $num, 'patient_id' => $patient_id,
            'montant_total' => $montant, 'montant_assurance' => $assurance,
            'montant_patient' => $patient_p, 'date_emission' => date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['user_id'],
        ], 'creation');
        logActivity("Facture $num créée", 'green', 'facture');
        $flash = ['green', "Facture $num créée avec succès."];
    }
}

//  CHANGER STATUT 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('factures.create') && post_str('action') === 'update_statut') {
    csrf_verify();
    $id     = post_int('facture_id');
    $statut = in_whitelist(post_str('statut'), ['en_attente','réglée','partielle','impayée','annulee'], 'en_attente');
    $date_r = ($statut === 'réglée') ? date('Y-m-d H:i:s') : null;
    db_exec("UPDATE factures SET statut=?, date_reglement=? WHERE id=?", [$statut, $date_r, $id]);
    //  Comptabilité : encaissement si réglée
    if ($statut === 'réglée') {
        $f = db_row("SELECT id, numero, montant_patient, date_reglement FROM factures WHERE id=?", [$id]);
        if ($f) {
            compta_on_facture($f + ['mode_paiement' => post_str('mode_paiement', 'especes'), 'regle_par' => $_SESSION['user_id']], 'reglement');
        }
    }
    logActivity("Facture #$id -> $statut", $statut === 'réglée' ? 'green' : 'blue', 'facture', $id);
    header('Location: ' . APP_URL . '/facturation.php?ok=1'); exit;
}

//  DONNEES 
require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('facturation');

$filtre = in_whitelist(get_str('filtre'), ['','en_attente','réglée','partielle','impayée','annulee'], '');
$search = get_str('search');
$where  = 'WHERE 1=1'; $params = [];
if ($filtre) { $where .= ' AND f.statut=?';  $params[] = $filtre; }
if ($search) {
    $like = '%'.$search.'%';
    $where .= ' AND (f.numero LIKE ? OR CONCAT(p.prenom," ",p.nom) LIKE ? OR f.assurance_type LIKE ?)';
    $params = array_merge($params, [$like, $like, $like]);
}

$_factPager = new Paginator([
    'sql'        => "SELECT f.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom, p.numero AS patient_num
     FROM factures f
     JOIN patients p ON p.id = f.patient_id
     $where",
    'count_sql'  => "SELECT COUNT(*) FROM factures f JOIN patients p ON p.id = f.patient_id $where",
    'params'     => $params,
    'sort_cols'  => [
        'numero'       => 'f.numero',
        'patient'      => 'patient_nom',
        'total'        => 'f.montant_total',
        'assurance'    => 'f.montant_assurance',
        'patient_part' => 'f.montant_patient',
        'couverture'   => 'f.assurance_type',
        'statut'       => 'f.statut',
        'date'         => 'f.date_emission',
        'reglement'    => 'f.date_reglement',
    ],
    'default_sort' => 'date',
    'default_dir'  => 'desc',
    'per_page'   => 20,
]);
$factures = $_factPager->load();
$patients = db_select("SELECT id, CONCAT(prenom,' ',nom) AS nom_complet, numero FROM patients ORDER BY nom LIMIT 300");

// KPIs
$year  = date('Y'); $month = date('m');
$kpis = [
    'ca_mois'     => (float)db_scalar("SELECT COALESCE(SUM(montant_total),0) FROM factures WHERE MONTH(date_emission)=? AND YEAR(date_emission)=? AND statut IN('réglée','partielle')", [$month,$year]),
    'attente'     => (float)db_scalar("SELECT COALESCE(SUM(montant_patient),0) FROM factures WHERE statut='en_attente'"),
    'impayées'    => (float)db_scalar("SELECT COALESCE(SUM(montant_patient),0) FROM factures WHERE statut='impayée'"),
    'nb_total'    => (int)db_scalar("SELECT COUNT(*) FROM factures"),
    'nb_réglée'   => (int)db_scalar("SELECT COUNT(*) FROM factures WHERE statut='réglée'"),
    'ca_annee'    => (float)db_scalar("SELECT COALESCE(SUM(montant_total),0) FROM factures WHERE YEAR(date_emission)=? AND statut IN('réglée','partielle')", [$year]),
];
$tauxRecouv = $kpis['nb_total'] > 0 ? round($kpis['nb_réglée'] / $kpis['nb_total'] * 100, 1) : 0;

// Répartition par statut
$statsByStatut = db_select("SELECT statut, COUNT(*) AS nb, COALESCE(SUM(montant_total),0) AS total FROM factures GROUP BY statut ORDER BY nb DESC");

// Evolution 12 mois
$evolution = db_select("SELECT DATE_FORMAT(date_emission,'%Y-%m') AS mois, COALESCE(SUM(montant_total),0) AS ca
    FROM factures WHERE date_emission >= DATE_SUB(NOW(),INTERVAL 12 MONTH) AND statut IN('réglée','partielle')
    GROUP BY mois ORDER BY mois ASC");

$sBadge = ['en_attente'=>'badge-yellow','réglée'=>'badge-green','partielle'=>'badge-blue','impayée'=>'badge-red','annulee'=>'badge-gray'];
$sLabel = ['en_attente'=>'⏳ En attente','réglée'=>'✅ Réglée','partielle'=>'Partielle','impayée'=>'❌ Impayée','annulee'=>'Annulée'];
$moisAbrev = ['01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Aoû','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Déc'];
?>

<?php if (isset($_GET['ok'])): ?><div class="alert alert-green alert-auto">Mis à jour.</div><?php endif; ?>
<?php if (!empty($flash)): ?><div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= h($flash[1]) ?></div><?php endif; ?>

<div class="page-header-row">
  <div><h2>Facturation & Finance</h2><p><?= $kpis['nb_total'] ?> factures &middot; Taux recouvrement : <?= $tauxRecouv ?>%</p></div>
  <?php if (can('factures.create')): ?>
  <button class="btn btn-blue" onclick="document.getElementById('modal-facture').style.display='flex'">+ Nouvelle facture</button>
  <?php endif; ?>
</div>

<!-- KPIs -->
<div class="stats-grid mb-24">
  <div class="stat-card green"><div class="stat-icon green">💰</div><div class="stat-value"><?= fmt_money($kpis['ca_mois']) ?></div><div class="stat-label">CA encaissé ce mois</div></div>
  <div class="stat-card blue"><div class="stat-icon blue">📈</div><div class="stat-value"><?= fmt_money($kpis['ca_annee']) ?></div><div class="stat-label">CA annéee <?= $year ?></div></div>
  <div class="stat-card yellow"><div class="stat-icon yellow">⏳</div><div class="stat-value"><?= fmt_money($kpis['attente']) ?></div><div class="stat-label">En attente paiement</div><div class="stat-delta"><?= $tauxRecouv ?>% taux recouvrement</div></div>
  <div class="stat-card red"><div class="stat-icon red">⚠️</div><div class="stat-value"><?= fmt_money($kpis['impayées']) ?></div><div class="stat-label">Impayés</div></div>
</div>

<div class="grid-2 mb-24">

  <!-- Répartition par statut -->
  <div class="card">
    <div class="card-header"><h3>Répartition par statut</h3></div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
      <?php
      $totalAll = array_sum(array_column($statsByStatut,'total')) ?: 1;
      foreach ($statsByStatut as $ss):
        $pct = round($ss['total']/$totalAll*100);
        $cols = ['en_attente'=>'var(--yellow)','réglée'=>'var(--green)','partielle'=>'var(--accent)','impayée'=>'var(--red)','annulee'=>'var(--text3)'];
        $col  = $cols[$ss['statut']] ?? 'var(--accent)';
      ?>
      <div>
        <div style="display:flex;justify-content:space-between;margin-bottom:5px">
          <div style="display:flex;align-items:center;gap:8px">
            <span class="badge <?= $sBadge[$ss['statut']]??'badge-gray' ?>"><?= $sLabel[$ss['statut']]??$ss['statut'] ?></span>
            <span style="font-size:12px;color:var(--text2)"><?= (int)$ss['nb'] ?> facture<?= $ss['nb']>1?'s':'' ?></span>
          </div>
          <span style="font-size:13px;font-weight:600"><?= fmt_money((float)$ss['total']) ?></span>
        </div>
        <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Evolution 12 mois -->
  <div class="card">
    <div class="card-header"><h3>CA encaissé &mdash; 12 derniers mois</h3></div>
    <div style="padding:20px">
      <?php if ($evolution):
        $maxCA = max(array_column($evolution,'ca')) ?: 1;
      ?>
      <div style="display:flex;align-items:flex-end;gap:5px;height:100px;margin-bottom:10px">
        <?php foreach ($evolution as $ev):
          $h = round($ev['ca']/$maxCA*100);
          [$yr,$mo] = explode('-',$ev['mois']);
          $isCurrent = $ev['mois'] === date('Y-m');
        ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px">
          <div style="width:100%;border-radius:3px 3px 0 0;height:<?= max(4,$h) ?>%;background:<?= $isCurrent?'var(--accent)':'rgba(var(--accent-rgb),.35)' ?>" title="<?= fmt_money((float)$ev['ca']) ?>"></div>
          <span style="font-size:9px;color:var(--text3)"><?= ($moisAbrev[$mo]??$mo).substr($yr,2) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="text-align:right;font-size:11px;color:var(--text3)">
        Total 12 mois : <strong style="color:var(--text)"><?= fmt_money(array_sum(array_column($evolution,'ca'))) ?></strong>
      </div>
      <?php else: ?><div style="text-align:center;color:var(--text3);font-size:12px">Aucune donnée</div><?php endif; ?>
    </div>
  </div>

</div>

<!-- Filtres -->
<div style="display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap">
  <form method="GET" style="display:flex;gap:6px;flex-wrap:wrap;flex:1">
    <input type="text" name="search" value="<?= h($search) ?>" placeholder="N° facture, patient, assurance..."
      style="flex:1;padding:8px 12px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text);font-size:13px;outline:none">
    <?php foreach ([''=>'Toutes','en_attente'=>'⏳ En attente','réglée'=>'Réglées','partielle'=>'Partielles','impayée'=>'Impayées','annulee'=>'Annulées'] as $v => $l): ?>
    <label style="display:flex;align-items:center;padding:7px 12px;background:var(--surface);border:1px solid <?= $filtre===$v?'var(--accent)':'var(--border)' ?>;border-radius:8px;cursor:pointer;font-size:12px;color:<?= $filtre===$v?'var(--accent2)':'var(--text2)' ?>">
      <input type="radio" name="filtre" value="<?= $v ?>" <?= $filtre===$v?'checked':'' ?> onchange="this.form.submit()" style="display:none"><?= $l ?>
    </label>
    <?php endforeach; ?>
    <?php if ($search): ?><a href="facturation.php" class="btn btn-ghost btn-sm">X</a><?php endif; ?>
  </form>
</div>

<!-- Table factures -->
<div class="card">
  <div class="card-header">
    <h3>Factures</h3>
    <span style="font-size:12px;color:var(--text2)"><?= $_factPager->total ?> résultat(s)</span>
  </div>
  <table>
    <thead>
      <tr><th><?= $_factPager->th('numero','N° Facture') ?></th><th><?= $_factPager->th('patient','Patient') ?></th><th><?= $_factPager->th('total','Total') ?></th><th><?= $_factPager->th('assurance','Assurance') ?></th><th><?= $_factPager->th('patient_part','Part patient') ?></th><th><?= $_factPager->th('couverture','Couverture') ?></th><th><?= $_factPager->th('statut','Statut') ?></th><th><?= $_factPager->th('date','Date') ?></th><th><?= $_factPager->th('reglement','Règlement') ?></th><?php if (can('factures.create')): ?><th>Action</th><?php endif; ?></tr>
    </thead>
    <tbody>
    <?php foreach ($factures as $f): ?>
    <tr>
      <td><strong><?= h($f['numero']) ?></strong></td>
      <td>
        <a href="dossiers.php?patient_id=<?= (int)$f['patient_id'] ?>" style="color:var(--text);text-decoration:none" title="Ouvrir le dossier">
          <?= h($f['patient_nom']) ?>
          <br><span class="text-xs text3"><?= h($f['patient_num']) ?></span>
        </a>
      </td>
      <td style="font-weight:700;color:var(--text)"><?= fmt_money((float)$f['montant_total']) ?></td>
      <td style="color:var(--accent2)"><?= fmt_money((float)$f['montant_assurance']) ?></td>
      <td style="color:var(--yellow)"><?= fmt_money((float)$f['montant_patient']) ?></td>
      <td style="font-size:12px;color:var(--text2)"><?= h($f['assurance_type']??'') ?></td>
      <td><span class="badge <?= $sBadge[$f['statut']]??'badge-gray' ?>"><?= $sLabel[$f['statut']]??$f['statut'] ?></span></td>
      <td style="font-size:12px"><?= fmt_date($f['date_emission'],true) ?></td>
      <td style="font-size:12px;color:var(--text2)"><?= $f['date_reglement']?fmt_date($f['date_reglement'],true):'' ?></td>
      <?php if (can('factures.create')): ?>
      <td>
        <form method="POST" style="display:flex;gap:4px">
          <input type="hidden" name="action" value="update_statut">
          <input type="hidden" name="facture_id" value="<?= (int)$f['id'] ?>">
          <?= csrf_field() ?>
          <select name="statut" onchange="this.form.submit()" style="padding:4px 6px;background:var(--bg);border:1px solid var(--border2);border-radius:5px;color:var(--text);font-size:11px;outline:none;cursor:pointer">
            <?php foreach ($sLabel as $sv => $sl): ?>
            <option value="<?= $sv ?>" <?= $f['statut']===$sv?'selected':'' ?>><?= $sl ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($factures)): ?>
    <tr><td colspan="10" style="text-align:center;padding:32px;color:var(--text3)">Aucune facture trouvée</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  <?= $_factPager->renderPagination() ?>
</div>

<!-- MODAL CREER FACTURE -->
<?php if (can('factures.create')): ?>
<div id="modal-facture" class="modal-overlay" style="display:none;z-index:200;align-items:center;justify-content:center" role="dialog" aria-modal="true" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(560px,95vw);max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:var(--surface)">
      <h3>Nouvelle facture</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('modal-facture').style.display='none'" aria-label="Fermer" style="font-size:18px;color:var(--text2)">X</button>
    </div>
    <form method="POST" style="padding:24px" onsubmit="calcPatient()">
      <input type="hidden" name="action" value="create_facture"><?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group form-full">
          <label>Patient *</label>
          <select name="patient_id" required style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value="">-- Selectionner --</option>
            <?php foreach ($patients as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= h($p['nom_complet']) ?> (<?= h($p['numero']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Montant total *</label>
          <input type="number" name="montant_total" id="inp-total" step="0.01" min="0" required oninput="calcPatient()">
        </div>
        <div class="form-group">
          <label>Part assurance</label>
          <input type="number" name="montant_assurance" id="inp-assurance" step="0.01" min="0" value="0" oninput="calcPatient()">
        </div>
        <div class="form-group">
          <label>Part patient (calculée)</label>
          <div id="part-patient" style="padding:9px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:7px;font-size:14px;font-weight:700;color:var(--yellow)">0.00</div>
        </div>
        <div class="form-group">
          <label>Type d'assurance</label>
          <input type="text" name="assurance_type" maxlength="100" list="assurance-list" placeholder="ex: CNSS, CNOPS, Privée...">
          <datalist id="assurance-list">
            <option>CNSS</option><option>CNOPS</option><option>RAMED</option>
            <option>AMO</option><option>Assurance privée</option><option>Mutuelle</option>
          </datalist>
        </div>
        <div class="form-group form-full">
          <label>Notes</label>
          <textarea name="notes" rows="2" maxlength="500" style="width:100%;padding:9px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;resize:vertical"></textarea>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-facture').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">Créer la facture</button>
      </div>
    </form>
  </div>
</div>
<script>
function calcPatient() {
  const total = parseFloat(document.getElementById('inp-total').value)||0;
  const ass   = parseFloat(document.getElementById('inp-assurance').value)||0;
  const part  = Math.max(0, total - ass);
  document.getElementById('part-patient').textContent = part.toFixed(2);
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php';
