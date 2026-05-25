<?php
$pageTitle = 'Tableau de bord — ' . APP_TITLE;
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout_top.php';

$stats = getDashboardStats();
$annee = date('Y'); $mois = (int)date('m');

// Articles pour le tableau de bord
$articles = query("SELECT * FROM articles WHERE actif=1 ORDER BY designation");

// Mouvements récents
$recents = query(
    "SELECT m.*, a.designation, a.unite, f.nom AS fournisseur_nom
     FROM mouvements m
     JOIN articles a ON m.article_id = a.id
     LEFT JOIN fournisseurs f ON m.fournisseur_id = f.id
     ORDER BY m.created_at DESC LIMIT 15"
);

// Données pour le graphique analyse croisée tous articles
$chartData = [];
for ($i = 1; $i <= 12; $i++) {
    $r = queryOne(
        "SELECT COALESCE(SUM(qte_entrees),0) AS e, COALESCE(SUM(qte_sorties),0) AS s 
         FROM analyse_mensuelle WHERE annee=? AND mois=?",
        [$annee, $i]
    );
    $chartData[] = ['mois' => MOIS_FR[$i], 'entrees' => (float)$r['e'], 'sorties' => (float)$r['s']];
}
?>

<div class="page-header">
  <h1>Tableau de bord</h1>
  <p>Projet: <strong><?= NOM_PROJET ?></strong> — Code: <strong><?= CODE_PROJET ?></strong> — <?= MOIS_FR[$mois] ?> <?= $annee ?></p>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card gold">
    <div class="stat-label">Articles en stock</div>
    <div class="stat-value"><?= $stats['nb_articles'] ?></div>
    <div class="stat-sub">Matériaux actifs</div>
    <div class="stat-icon">📦</div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Valeur du stock</div>
    <div class="stat-value money"><?= number_format($stats['valeur_totale'], 0, ',', ' ') ?></div>
    <div class="stat-sub">FCFA — au CMUPACE</div>
    <div class="stat-icon">💰</div>
  </div>
  <div class="stat-card blue">
    <div class="stat-label">Entrées ce mois</div>
    <div class="stat-value money"><?= number_format($stats['entrees_mois'], 0, ',', ' ') ?></div>
    <div class="stat-sub">FCFA</div>
    <div class="stat-icon">⬇</div>
  </div>
  <div class="stat-card red">
    <div class="stat-label">Sorties ce mois</div>
    <div class="stat-value money"><?= number_format($stats['sorties_mois'], 0, ',', ' ') ?></div>
    <div class="stat-sub">FCFA</div>
    <div class="stat-icon">⬆</div>
  </div>
</div>

<!-- Alertes -->
<?php if (!empty($stats['alertes'])): ?>
<div class="alert alert-warn mb-16">
  <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  <div>
    <strong>Stock bas:</strong>
    <?= implode(', ', array_map(fn($a) => htmlspecialchars($a['designation']) . ' (' . $a['stock_actuel'] . ')', $stats['alertes'])) ?>
  </div>
</div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:24px;">
  <!-- Chart -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Entrées/Sorties <?= $annee ?></span>
      <span class="text-muted" style="font-size:11px;">Toutes matières confondues</span>
    </div>
    <div class="card-body">
      <div class="chart-container">
        <canvas id="chartMouvements"></canvas>
      </div>
    </div>
  </div>

  <!-- Stock état -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">État du Stock</span>
      <a href="/pages/stock.php" class="btn btn-secondary btn-sm">Détails</a>
    </div>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Matériau</th>
            <th class="text-right">Stock</th>
            <th class="text-right">Valeur</th>
            <th class="text-right">CMUPACE</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($articles as $art): ?>
          <tr>
            <td class="bold"><?= htmlspecialchars($art['designation']) ?></td>
            <td class="mono text-right <?= $art['stock_actuel'] <= $art['stock_alerte'] && $art['stock_alerte'] > 0 ? 'text-red' : 'text-green' ?>">
              <?= number_format($art['stock_actuel'], 0, ',', ' ') ?> <?= htmlspecialchars($art['unite']) ?>
            </td>
            <td class="amount"><?= number_format($art['valeur_stock'], 0, ',', ' ') ?></td>
            <td class="mono text-right"><?= number_format($art['cmupace'], 0, ',', ' ') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Mouvements récents -->
<div class="card">
  <div class="card-header">
    <span class="card-title">Mouvements récents</span>
    <a href="/pages/mouvements.php" class="btn btn-secondary btn-sm">Voir tout</a>
  </div>
  <div class="table-wrap" style="border:none; border-radius:0;">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Type</th>
          <th>Matériau</th>
          <th>Réf. BSM/BC</th>
          <th class="text-right">Qté</th>
          <th class="text-right">PU</th>
          <th class="text-right">Montant</th>
          <th>Affectation</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($recents as $m): ?>
        <tr class="<?= $m['type_mouvement'] === 'entree' ? 'entree-row' : 'sortie-row' ?>">
          <td class="mono"><?= date('d/m/Y', strtotime($m['date_mouvement'])) ?></td>
          <td class="type-cell">
            <span class="badge <?= $m['type_mouvement'] === 'entree' ? 'badge-green' : 'badge-red' ?>">
              <?= strtoupper($m['type_mouvement']) ?>
            </span>
          </td>
          <td class="bold"><?= htmlspecialchars($m['designation']) ?></td>
          <td class="mono text-muted"><?= htmlspecialchars($m['bsm'] ?? $m['bcl_fact'] ?? $m['da_bc'] ?? '—') ?></td>
          <td class="mono text-right"><?= number_format($m['quantite'], 2, ',', ' ') ?> <?= $m['unite'] ?></td>
          <td class="mono text-right"><?= number_format($m['prix_unitaire'], 0, ',', ' ') ?></td>
          <td class="amount"><?= number_format($m['montant'], 0, ',', ' ') ?></td>
          <td class="text-muted"><?= htmlspecialchars($m['fournisseur_nom'] ?? $m['affectation_libre'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($recents)): ?>
        <tr><td colspan="8" class="text-center text-muted" style="padding:24px;">Aucun mouvement enregistré</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const chartData = <?= json_encode($chartData) ?>;
const ctx = document.getElementById('chartMouvements')?.getContext('2d');
if (ctx) {
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: chartData.map(d => d.mois.substring(0,3)),
      datasets: [
        {
          label: 'Entrées',
          data: chartData.map(d => d.entrees),
          backgroundColor: 'rgba(63,185,80,0.7)',
          borderRadius: 3,
        },
        {
          label: 'Sorties',
          data: chartData.map(d => d.sorties),
          backgroundColor: 'rgba(248,81,73,0.7)',
          borderRadius: 3,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: '#8b949e', font: { size: 11 } } },
        tooltip: {
          callbacks: {
            label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y.toLocaleString('fr-FR')
          }
        }
      },
      scales: {
        x: { ticks: { color: '#8b949e', font: { size: 10 } }, grid: { color: '#21262d' } },
        y: { ticks: { color: '#8b949e', font: { size: 10 } }, grid: { color: '#21262d' } }
      }
    }
  });
}
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
