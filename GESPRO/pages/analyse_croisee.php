<?php
$pageTitle = 'Analyse Croisée — ' . APP_TITLE;
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout_top.php';

$annee     = (int)($_GET['annee'] ?? date('Y'));
$articleId = (int)($_GET['article'] ?? 0);
$articles  = query("SELECT * FROM articles WHERE actif=1 ORDER BY designation");

if (!$articleId && !empty($articles)) $articleId = (int)$articles[0]['id'];
$article = $articleId ? queryOne("SELECT * FROM articles WHERE id=?", [$articleId]) : null;

// Données analyse croisée pour tous les mois
$analyseData = [];
for ($m = 1; $m <= 12; $m++) {
    if ($articleId) {
        $row = queryOne(
            "SELECT * FROM analyse_mensuelle WHERE article_id=? AND annee=? AND mois=?",
            [$articleId, $annee, $m]
        );
        $analyseData[] = [
            'mois'    => $m,
            'label'   => MOIS_FR[$m],
            'short'   => substr(MOIS_FR[$m], 0, 3),
            'entrees' => (float)($row['qte_entrees'] ?? 0),
            'sorties' => (float)($row['qte_sorties'] ?? 0),
            'stock'   => (float)($row['qte_stock'] ?? 0),
            'mt_e'    => (float)($row['montant_entrees'] ?? 0),
            'mt_s'    => (float)($row['montant_sorties'] ?? 0),
            'marge'   => (float)($row['marge'] ?? 0),
        ];
    }
}
?>

<div class="page-header">
  <h1>Analyse Croisée Dynamique de Stock</h1>
  <p>Vue annuelle par matériau — entrées, sorties, stocks et valorisation</p>
</div>

<div class="filter-bar mb-24">
  <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap;">
    <select name="article" class="form-control" onchange="this.form.submit()">
      <?php foreach ($articles as $a): ?>
      <option value="<?= $a['id'] ?>" <?= $a['id'] == $articleId ? 'selected' : '' ?>>
        <?= htmlspecialchars($a['designation']) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <select name="annee" class="form-control" onchange="this.form.submit()">
      <?php for ($y = 2024; $y <= date('Y') + 1; $y++): ?>
      <option value="<?= $y ?>" <?= $y == $annee ? 'selected' : '' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
    <button onclick="printPage()" class="btn btn-secondary btn-sm ml-auto">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
      Imprimer
    </button>
  </form>
</div>

<?php if ($article): ?>

<!-- Titre article -->
<div class="fiche-header mb-16">
  <div>
    <div class="fiche-title">ANALYSE CROISÉE — <?= strtoupper(htmlspecialchars($article['designation'])) ?></div>
    <div class="fiche-meta">
      <span><strong>PROJET :</strong> <?= NOM_PROJET ?> | <strong>CODE :</strong> <?= CODE_PROJET ?></span>
      <span><strong>ANNÉE :</strong> <?= $annee ?> | <strong>CMUPACE :</strong> <?= number_format($article['cmupace'], 0, ',', ' ') ?> FCFA</span>
    </div>
  </div>
  <div style="text-align:center;">
    <div style="font-size:10px; color:var(--text3); text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Stock actuel</div>
    <div style="font-family:var(--font-cond); font-size:24px; font-weight:800; color:var(--accent);">
      <?= number_format($article['stock_actuel'], 0, ',', ' ') ?>
    </div>
    <div style="font-size:11px; color:var(--text3);"><?= htmlspecialchars($article['unite']) ?></div>
  </div>
  <div style="text-align:right;">
    <div style="font-size:10px; color:var(--text3); text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Valeur stock</div>
    <div style="font-family:var(--font-cond); font-size:20px; font-weight:700; color:var(--blue);">
      <?= number_format($article['valeur_stock'], 0, ',', ' ') ?> FCFA
    </div>
  </div>
</div>

<!-- Graphique -->
<div class="card mb-24">
  <div class="card-header">
    <span class="card-title">Évolution mensuelle <?= $annee ?></span>
  </div>
  <div class="card-body">
    <div class="chart-container" style="height:280px;">
      <canvas id="analyseChart"></canvas>
    </div>
  </div>
</div>

<!-- Tableau QUANTITÉS -->
<div class="card mb-16">
  <div class="card-header">
    <span class="card-title">QUANTITÉS</span>
    <span class="badge badge-gold">En <?= htmlspecialchars($article['unite']) ?></span>
  </div>
  <div class="table-wrap" style="border:none; border-radius:0;">
    <table>
      <thead>
        <tr>
          <th style="min-width:100px;">Indicateur</th>
          <?php foreach ($analyseData as $d): ?>
          <th class="text-right" style="min-width:80px;"><?= $d['short'] ?></th>
          <?php endforeach; ?>
          <th class="text-right" style="background:var(--bg4);">TOTAL</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="bold text-green">Entrées</td>
          <?php $totE = 0; foreach ($analyseData as $d): $totE += $d['entrees']; ?>
          <td class="mono text-right <?= $d['entrees'] > 0 ? 'text-green' : 'text-muted' ?>">
            <?= $d['entrees'] > 0 ? number_format($d['entrees'], 0, ',', ' ') : '—' ?>
          </td>
          <?php endforeach; ?>
          <td class="amount text-green" style="background:var(--bg4);"><?= number_format($totE, 0, ',', ' ') ?></td>
        </tr>
        <tr>
          <td class="bold text-red">Sorties</td>
          <?php $totS = 0; foreach ($analyseData as $d): $totS += $d['sorties']; ?>
          <td class="mono text-right <?= $d['sorties'] > 0 ? 'text-red' : 'text-muted' ?>">
            <?= $d['sorties'] > 0 ? number_format($d['sorties'], 0, ',', ' ') : '—' ?>
          </td>
          <?php endforeach; ?>
          <td class="amount text-red" style="background:var(--bg4);"><?= number_format($totS, 0, ',', ' ') ?></td>
        </tr>
        <tr style="background:var(--accent3);">
          <td class="bold text-accent">Stocks</td>
          <?php foreach ($analyseData as $d): ?>
          <td class="mono text-right <?= $d['stock'] > 0 ? 'text-accent' : ($d['stock'] < 0 ? 'text-red' : 'text-muted') ?>">
            <?= $d['stock'] != 0 ? number_format($d['stock'], 0, ',', ' ') : '—' ?>
          </td>
          <?php endforeach; ?>
          <td class="amount text-accent" style="background:rgba(212,160,23,0.15);">—</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Tableau VALORISATION -->
<div class="card mb-24">
  <div class="card-header">
    <span class="card-title">VALORISATION</span>
    <span class="badge badge-blue">En FCFA</span>
  </div>
  <div class="table-wrap" style="border:none; border-radius:0;">
    <table>
      <thead>
        <tr>
          <th style="min-width:100px;">Coûts</th>
          <?php foreach ($analyseData as $d): ?>
          <th class="text-right" style="min-width:80px;"><?= $d['short'] ?></th>
          <?php endforeach; ?>
          <th class="text-right" style="background:var(--bg4);">TOTAL</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="bold text-green">Entrées</td>
          <?php $totME = 0; foreach ($analyseData as $d): $totME += $d['mt_e']; ?>
          <td class="amount text-right <?= $d['mt_e'] > 0 ? 'text-green' : 'text-muted' ?>">
            <?= $d['mt_e'] > 0 ? number_format($d['mt_e'], 0, ',', ' ') : '—' ?>
          </td>
          <?php endforeach; ?>
          <td class="amount text-green" style="background:var(--bg4);"><?= number_format($totME, 0, ',', ' ') ?></td>
        </tr>
        <tr>
          <td class="bold text-red">Sorties</td>
          <?php $totMS = 0; foreach ($analyseData as $d): $totMS += $d['mt_s']; ?>
          <td class="amount text-right <?= $d['mt_s'] > 0 ? 'text-red' : 'text-muted' ?>">
            <?= $d['mt_s'] > 0 ? number_format($d['mt_s'], 0, ',', ' ') : '—' ?>
          </td>
          <?php endforeach; ?>
          <td class="amount text-red" style="background:var(--bg4);"><?= number_format($totMS, 0, ',', ' ') ?></td>
        </tr>
        <tr style="background:var(--blue2);">
          <td class="bold text-blue">Marge valeur</td>
          <?php foreach ($analyseData as $d): ?>
          <td class="amount text-right <?= $d['marge'] >= 0 ? 'text-blue' : 'text-red' ?>">
            <?= $d['marge'] != 0 ? number_format($d['marge'], 0, ',', ' ') : '—' ?>
          </td>
          <?php endforeach; ?>
          <td class="amount text-blue" style="background:rgba(88,166,255,0.2);"><?= number_format($totME - $totMS, 0, ',', ' ') ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Zone signatures -->
<div class="signature-zone">
  <div class="signature-grid">
    <div class="sig-box"><div class="title">Asst. du Gest. des Stocks</div><div class="line"></div></div>
    <div class="sig-box"><div class="title">Gestionnaire des Stocks</div><div class="line"></div></div>
    <div class="sig-box"><div class="title">R.A.F</div><div class="line"></div></div>
    <div class="sig-box"><div class="title">Directeur des Travaux</div><div class="line"></div></div>
  </div>
</div>

<script>
const data = <?= json_encode($analyseData) ?>;
const ctx = document.getElementById('analyseChart')?.getContext('2d');
if (ctx) {
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: data.map(d => d.short),
      datasets: [
        { label: 'Entrées', data: data.map(d => d.entrees), backgroundColor: 'rgba(63,185,80,0.75)', borderRadius: 4 },
        { label: 'Sorties', data: data.map(d => d.sorties), backgroundColor: 'rgba(248,81,73,0.75)', borderRadius: 4 },
        { label: 'Stock', data: data.map(d => d.stock), type: 'line', borderColor: '#d4a017', backgroundColor: 'rgba(212,160,23,0.1)', tension: 0.4, fill: true, yAxisID: 'y1', pointRadius: 4, pointBackgroundColor: '#d4a017' }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { labels: { color: '#8b949e', font: { size: 11 } } } },
      scales: {
        x: { ticks: { color: '#8b949e' }, grid: { color: '#21262d' } },
        y: { ticks: { color: '#8b949e' }, grid: { color: '#21262d' }, position: 'left' },
        y1: { ticks: { color: '#d4a017' }, grid: { drawOnChartArea: false }, position: 'right' }
      }
    }
  });
}
</script>

<?php else: ?>
<div class="empty-state"><p>Aucun article disponible.</p></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
