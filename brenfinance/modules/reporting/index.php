<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('reporting');
$pageTitle = 'Reporting & Tableaux de bord';

$db = getDB();

// Data for charts
$moisLabels = [];
$entreesMois = [];
$sortiesMois = [];
for ($i = 5; $i >= 0; $i--) {
    $mois = date('Y-m', strtotime("-$i months"));
    $moisLabels[] = date('M Y', strtotime("-$i months"));
    $r = $db->prepare("SELECT SUM(CASE WHEN sens='credit' THEN montant ELSE 0 END) as e, SUM(CASE WHEN sens='debit' THEN montant ELSE 0 END) as s FROM operations_caisse WHERE DATE_FORMAT(date_operation,'%Y-%m')=? AND annule=0");
    $r->execute([$mois]);
    $data = $r->fetch();
    $entreesMois[] = round($data['e']??0);
    $sortiesMois[] = round($data['s']??0);
}

// Dépenses par service
$depServ = $db->query("SELECT s.nom, SUM(oc.montant) as total FROM operations_caisse oc LEFT JOIN services s ON oc.service_id=s.id WHERE oc.sens='debit' AND oc.annule=0 AND YEAR(oc.date_operation)=YEAR(CURDATE()) GROUP BY s.nom ORDER BY total DESC LIMIT 8")->fetchAll();

// Soldes summary
$totalCaisse = $db->query("SELECT SUM(solde_actuel) as t FROM caisses WHERE statut!='suspendue'")->fetch()['t']??0;
$totalBanque = $db->query("SELECT SUM(solde_actuel) as t FROM comptes_bancaires WHERE statut='actif'")->fetch()['t']??0;
$totalEng    = $db->query("SELECT SUM(montant) as t FROM demandes_engagement WHERE statut NOT IN ('rejete','annule') AND YEAR(created_at)=YEAR(CURDATE())")->fetch()['t']??0;

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
  <div><h1>Reporting & Tableaux de bord</h1><p>Analyse et suivi financier en temps réel</p></div>
  <button class="btn btn-outline" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer</button>
</div>

<!-- KPI Row -->
<div class="stats-grid mb-24">
  <div class="stat-card">
    <div class="stat-icon"><i class="fa-solid fa-coins"></i></div>
    <div class="stat-label">Trésorerie caisse</div>
    <div class="stat-value"><?= formatMontant($totalCaisse) ?></div>
  </div>
  <div class="stat-card info">
    <div class="stat-icon"><i class="fa-solid fa-building-columns"></i></div>
    <div class="stat-label">Trésorerie banque</div>
    <div class="stat-value"><?= formatMontant($totalBanque) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
    <div class="stat-label">Trésorerie globale</div>
    <div class="stat-value"><?= formatMontant($totalCaisse + $totalBanque) ?></div>
    <div class="stat-sub">Caisse + Banque</div>
  </div>
  <div class="stat-card warning">
    <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
    <div class="stat-label">Engagements en cours</div>
    <div class="stat-value"><?= formatMontant($totalEng) ?></div>
    <div class="stat-sub">Exercice <?= date('Y') ?></div>
  </div>
</div>

<?php
$tab = $_GET['tab'] ?? 'overview';
$exerciceCourant = getExerciceCourant();
$exerciceId = $exerciceCourant ? (int)$exerciceCourant['id'] : 0;
$sig = $exerciceId ? calculerSIG($exerciceId) : [];
$ratios = $exerciceId ? calculerRatios($exerciceId) : [];
$sigN1 = [];
if ($exerciceId) {
    $prevExercice = $db->query("SELECT id FROM exercices WHERE date_fin < '{$exerciceCourant['date_debut']}' ORDER BY date_fin DESC LIMIT 1")->fetch();
    if ($prevExercice) $sigN1 = calculerSIG((int)$prevExercice['id']);
}
?>

<!-- Tab switcher -->
<div class="mb-20" style="display:flex;gap:4px">
    <a href="?tab=overview" class="btn <?= $tab === 'overview' ? 'btn-primary' : 'btn-outline' ?> btn-sm"><i class="fa-solid fa-chart-bar"></i> Vue d'ensemble</a>
    <a href="?tab=sig" class="btn <?= $tab === 'sig' ? 'btn-primary' : 'btn-outline' ?> btn-sm"><i class="fa-solid fa-table-list"></i> SIG & Ratios</a>
</div>

<?php if ($tab === 'overview'): ?>

<!-- Charts -->
<div class="dashboard-split mb-20" style="display:grid;grid-template-columns:2fr 1fr;gap:16px">
  <div class="card">
    <div class="card-header"><span class="card-title">Flux de caisse — 6 derniers mois</span></div>
    <div class="card-body">
      <canvas id="chartFlux" height="120"></canvas>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><span class="card-title">Dépenses par service</span></div>
    <div class="card-body">
      <canvas id="chartServs" height="180"></canvas>
    </div>
  </div>
</div>

<!-- Recent data tables -->
<div class="card mb-20">
  <div class="card-header"><span class="card-title">Synthèse des caisses</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Caisse</th><th>Code</th><th>Statut</th><th>Solde actuel</th><th class="hide-mobile">Solde initial</th><th class="hide-mobile">Variation</th></tr></thead>
      <tbody>
        <?php $caisses = $db->query("SELECT * FROM caisses ORDER BY libelle")->fetchAll();
        foreach($caisses as $c):
          $variation = $c['solde_actuel'] - $c['solde_initial'];
        ?>
        <tr>
          <td><?= sanitize($c['libelle']) ?></td>
          <td><code><?= sanitize($c['code']) ?></code></td>
          <td><span class="badge <?= $c['statut']==='ouverte'?'badge-success':($c['statut']==='fermee'?'badge-gray':'badge-danger') ?>"><?= ucfirst($c['statut']) ?></span></td>
          <td class="amount fw-bold"><?= formatMontant($c['solde_actuel']) ?></td>
          <td class="amount"><?= formatMontant($c['solde_initial']) ?></td>
          <td class="amount <?= $variation>=0?'amount-credit':'amount-debit' ?>"><?= ($variation>=0?'+':'') . number_format($variation,0,',',' ') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const labels = <?= json_encode($moisLabels) ?>;
const entrees = <?= json_encode($entreesMois) ?>;
const sorties = <?= json_encode($sortiesMois) ?>;
const depServLabels = <?= json_encode(array_column($depServ,'nom')) ?>;
const depServData = <?= json_encode(array_column($depServ,'total')) ?>;

Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = '#4b5671';

new Chart(document.getElementById('chartFlux'), {
  type: 'bar',
  data: {
    labels,
    datasets: [
      { label: 'Entrées', data: entrees, backgroundColor: 'rgba(26,158,90,.7)', borderRadius: 4 },
      { label: 'Sorties', data: sorties, backgroundColor: 'rgba(214,53,71,.7)', borderRadius: 4 }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: true,
    plugins: { legend: { position: 'top' } },
    scales: {
      y: { ticks: { callback: v => new Intl.NumberFormat('fr-CM',{notation:'compact'}).format(v) + ' F' }, grid: { color: '#eef0f3' } },
      x: { grid: { display: false } }
    }
  }
});

if (depServLabels.length) {
  new Chart(document.getElementById('chartServs'), {
    type: 'doughnut',
    data: {
      labels: depServLabels.map(l => l || 'Non classé'),
      datasets: [{ data: depServData, backgroundColor: ['#1a4f8a','#0ea87e','#e88c2d','#d63547','#7c3aed','#0891b2','#059669','#dc2626'], borderWidth: 2, borderColor: '#fff' }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom', labels: { padding: 12, font: { size: 11 } } },
        tooltip: { callbacks: { label: ctx => ' ' + new Intl.NumberFormat('fr-CM').format(ctx.raw) + ' FCFA' } }
      }
    }
  });
} else {
  document.getElementById('chartServs').parentElement.innerHTML = '<div class="empty-state" style="padding:40px"><p>Aucune dépense par service enregistrée</p></div>';
}
</script>

<?php elseif ($tab === 'sig'): ?>

<?php if ($exerciceCourant): ?>
<div class="card mb-20">
  <div class="card-header"><span class="card-title">Soldes Intermédiaires de Gestion — Exercice <?= htmlspecialchars($exerciceCourant['code']) ?></span></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Code</th><th>Libellé</th><th>N (<?= htmlspecialchars($exerciceCourant['code']) ?>)</th><th>N-1</th><th>Variation</th><th>Évolution</th></tr>
      </thead>
      <tbody>
        <?php foreach ($sig as $i => $s):
          $n1 = $sigN1[$i]['montant'] ?? 0;
          $variation = $s['montant'] - $n1;
          $evol = $n1 != 0 ? round(($variation / abs($n1)) * 100, 1) : ($s['montant'] != 0 ? 100 : 0);
        ?>
        <tr>
          <td style="font-weight:700"><?= htmlspecialchars($s['code']) ?></td>
          <td><?= htmlspecialchars($s['libelle']) ?></td>
          <td class="amount"><?= formatMontant($s['montant']) ?></td>
          <td class="amount" style="color:var(--text3)"><?= $sigN1 ? formatMontant($n1) : '—' ?></td>
          <td class="amount <?= $variation >= 0 ? 'amount-credit' : 'amount-debit' ?>">
            <?= ($variation >= 0 ? '+' : '') . formatMontant($variation) ?>
          </td>
          <td>
            <?php if ($sigN1): ?>
            <span class="badge <?= $evol >= 0 ? 'badge-success' : 'badge-danger' ?>">
              <?= ($evol >= 0 ? '↑' : '↓') . ' ' . abs($evol) . '%' ?>
            </span>
            <?php else: ?>
            <span style="color:var(--text3)">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mb-20">
  <div class="card-header"><span class="card-title">Ratios financiers</span></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Code</th><th>Libellé</th><th>Valeur</th><th>Norme</th><th>Interprétation</th></tr>
      </thead>
      <tbody>
        <?php foreach ($ratios as $r): ?>
        <tr>
          <td style="font-weight:700"><?= htmlspecialchars($r['code']) ?></td>
          <td><?= htmlspecialchars($r['libelle']) ?></td>
          <td class="amount">
            <span style="color:<?= $r['ok'] ? 'var(--success)' : 'var(--danger)' ?>;font-weight:600">
              <?= number_format($r['valeur'], $r['valeur'] == (int)$r['valeur'] ? 0 : 2, ',', ' ') ?><?= in_array($r['code'], ['DF/EBE']) ? ' ans' : '%' ?>
            </span>
          </td>
          <td style="color:var(--text3)"><?= htmlspecialchars($r['norme']) ?></td>
          <td style="font-size:13px;color:var(--text2)"><?= htmlspecialchars($r['interpretation']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mb-20">
  <div class="card-header"><span class="card-title">Définitions</span></div>
  <div class="card-body" style="font-size:13px;color:var(--text2);line-height:1.8">
    <p><strong>Marge nette (RN/CA) :</strong> Part du résultat net dans le chiffre d'affaires. Mesure la profitabilité globale.</p>
    <p><strong>Taux de marge brute (EBE/VA) :</strong> Richesse générée par l'activité après rémunération du personnel.</p>
    <p><strong>Rentabilité financière (ROE) :</strong> Rendement des capitaux propres. Attendu > 10% par les investisseurs.</p>
    <p><strong>Capacité de remboursement :</strong> Nombre d'années d'EBE nécessaires pour rembourser l'endettement. Un ratio < 3 est sain.</p>
    <p><strong>Marge opérationnelle :</strong> Performance de l'exploitation hors éléments financiers et exceptionnels.</p>
  </div>
</div>

<?php else: ?>
<div class="empty-state" style="padding:60px;text-align:center">
  <i class="fa-solid fa-calendar-xmark" style="font-size:48px;color:var(--text3);margin-bottom:16px"></i>
  <h3>Aucun exercice ouvert</h3>
  <p style="color:var(--text2)">
    Les SIG et ratios ne peuvent être calculés que lorsqu'un exercice comptable est ouvert.<br>
    Veuillez créer un exercice dans le module <strong>Exercices</strong>.
  </p>
</div>
<?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
