<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requirePermission('reports');

$pageTitle = 'Rapports & Analyses';
$saleModel = new Sale();
$storeId = currentStoreId();

$year      = (int)($_GET['year']      ?? date('Y'));
$dateFrom  = sanitize($_GET['date_from'] ?? date('Y-m-01'));
$dateTo    = sanitize($_GET['date_to']   ?? date('Y-m-d'));

$monthlyData  = $saleModel->getMonthlyRevenue($storeId, $year);
$topProducts  = $saleModel->getTopProducts($storeId, 10, $dateFrom, $dateTo);
$storeRevenue = $saleModel->getRevenueByStore();

$db = Database::getInstance();

// Ventes par jour (30 derniers jours)
$dailyStmt = $db->prepare(
    "SELECT DATE(sale_date) as day, COUNT(*) as cnt, SUM(total_amount) as revenue
     FROM sales WHERE store_id = ? AND status = 'completed'
     AND DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     GROUP BY DATE(sale_date) ORDER BY day"
);
$dailyStmt->execute([$storeId]);
$dailyData = $dailyStmt->fetchAll();

// Stats globales période
$periodStmt = $db->prepare(
    "SELECT COUNT(*) as total_sales,
     SUM(total_amount) as revenue,
     SUM(total_amount - tax_amount - discount_amount) as net,
     SUM((SELECT SUM(si.quantity * (si.unit_price - si.cost_price))
          FROM sale_items si WHERE si.sale_id = s.id)) as profit,
     AVG(total_amount) as avg_sale
     FROM sales s WHERE store_id = ? AND status = 'completed'
     AND DATE(sale_date) BETWEEN ? AND ?"
);
$periodStmt->execute([$storeId, $dateFrom, $dateTo]);
$periodStats = $periodStmt->fetch();

// Préparer données graphiques
$months = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
$revenueByMonth = array_fill(0, 12, 0);
$salesByMonth   = array_fill(0, 12, 0);
foreach ($monthlyData as $m) {
    $revenueByMonth[(int)$m['month'] - 1] = (float)$m['revenue'];
    $salesByMonth[(int)$m['month'] - 1]   = (int)$m['count'];
}

// Daily labels & data
$dailyLabels   = array_column($dailyData, 'day');
$dailyRevenues = array_column($dailyData, 'revenue');

require_once __DIR__ . '/layout_top.php';
?>

<!-- Filtres -->
<div class="card mb-3">
    <div class="card-body py-2 px-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-1" style="font-size:.75rem;font-weight:600">Période</label>
                <div class="d-flex gap-2">
                    <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="form-control form-control-sm" style="border-radius:8px">
                    <span class="d-flex align-items-center text-muted">→</span>
                    <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="form-control form-control-sm" style="border-radius:8px">
                </div>
            </div>
            <div class="col-auto">
                <label class="form-label mb-1" style="font-size:.75rem;font-weight:600">Année</label>
                <select name="year" class="form-select form-select-sm" style="border-radius:8px">
                    <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary" style="border-radius:8px">Appliquer</button>
            </div>
            <div class="col-auto ms-auto">
                <div class="btn-group btn-group-sm">
                    <a href="?date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?>&year=<?= $year ?>" class="btn btn-outline-secondary" style="font-size:.72rem">Aujourd'hui</a>
                    <a href="?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-d') ?>&year=<?= $year ?>" class="btn btn-outline-secondary" style="font-size:.72rem">Ce mois</a>
                    <a href="?date_from=<?= date('Y-01-01') ?>&date_to=<?= date('Y-12-31') ?>&year=<?= $year ?>" class="btn btn-outline-secondary" style="font-size:.72rem">Cette année</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php $hasData = (($periodStats['total_sales'] ?? 0) > 0) || !empty($monthlyData) || !empty($dailyData); ?>

<!-- KPIs période -->
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="stat-card accent">
            <div class="stat-icon accent"><i class="bi bi-receipt"></i></div>
            <div class="stat-value"><?= number_format((int)($periodStats['total_sales'] ?? 0)) ?></div>
            <div class="stat-label">Ventes sur période</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card success">
            <div class="stat-icon success"><i class="bi bi-cash-stack"></i></div>
            <div class="stat-value" style="font-size:1.1rem"><?= formatMoney((float)($periodStats['revenue'] ?? 0)) ?></div>
            <div class="stat-label">Chiffre d'affaires</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card warning">
            <div class="stat-icon warning"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="stat-value" style="font-size:1.1rem"><?= formatMoney((float)($periodStats['profit'] ?? 0)) ?></div>
            <div class="stat-label">Bénéfice estimé</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card danger">
            <div class="stat-icon danger"><i class="bi bi-calculator"></i></div>
            <div class="stat-value" style="font-size:1.1rem"><?= formatMoney((float)($periodStats['avg_sale'] ?? 0)) ?></div>
            <div class="stat-label">Panier moyen</div>
        </div>
    </div>
</div>

<?php if (!$hasData): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-bar-chart" style="font-size:3rem;color:var(--text-muted);opacity:.4"></i>
        <p class="mt-3 mb-0 text-muted" style="font-size:.9rem">Aucune donnée pour cette période. Les graphiques apparaîtront dès les premières ventes.</p>
    </div>
</div>
<?php else: ?>

<div class="row g-3 mb-3">
    <!-- Revenu mensuel -->
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header-custom">
                <h6><i class="bi bi-bar-chart me-2 text-primary"></i>Revenu Mensuel <?= $year ?></h6>
                <div class="d-flex gap-2">
                    <span class="badge" style="background:rgba(99,102,241,0.1);color:var(--accent);font-size:.72rem"><i class="bi bi-square-fill me-1"></i>Revenu</span>
                    <span class="badge" style="background:rgba(16,185,129,0.1);color:#10B981;font-size:.72rem"><i class="bi bi-square-fill me-1"></i>Ventes</span>
                </div>
            </div>
            <div class="card-body p-3">
                <canvas id="monthlyChart" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <!-- Courbe 30 jours -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header-custom">
                <h6><i class="bi bi-graph-up me-2 text-success"></i>Revenu — 30 derniers jours</h6>
            </div>
            <div class="card-body p-3">
                <canvas id="dailyChart" height="180"></canvas>
            </div>
        </div>
    </div>

    <!-- Top produits -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header-custom">
                <h6><i class="bi bi-trophy me-2 text-warning"></i>Top Produits</h6>
            </div>
            <div class="card-body p-0" style="max-height:340px;overflow-y:auto">
                <?php foreach ($topProducts as $i => $p): ?>
                <?php $pct = $periodStats['revenue'] > 0 ? round(($p['total_revenue'] / $periodStats['revenue']) * 100, 1) : 0; ?>
                <div class="px-3 py-2" style="border-bottom:1px solid var(--border)">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div class="d-flex align-items-center gap-2">
                            <span style="width:20px;height:20px;border-radius:50%;background:<?= $i < 3 ? 'linear-gradient(135deg,#F59E0B,#FCD34D)' : 'rgba(99,102,241,0.1)' ?>;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;color:<?= $i < 3 ? '#fff' : 'var(--accent)' ?>;flex-shrink:0"><?= $i+1 ?></span>
                            <span style="font-size:.8rem;font-weight:500"><?= e($p['product_name']) ?></span>
                        </div>
                        <span style="font-size:.78rem;font-weight:600;color:var(--accent)"><?= formatMoney((float)$p['total_revenue']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between" style="font-size:.7rem;color:var(--text-muted);padding-left:28px">
                        <span>Qté: <?= number_format((float)$p['total_qty']) ?></span>
                        <span>Profit: <?= formatMoney((float)$p['total_profit']) ?></span>
                    </div>
                    <div style="height:3px;background:var(--border);border-radius:3px;margin-top:4px;margin-left:28px">
                        <div style="height:100%;width:<?= min(100, $pct) ?>%;background:var(--accent);border-radius:3px;transition:width .3s"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($topProducts)): ?>
                <div class="text-center py-4 text-muted" style="font-size:.85rem">Aucune vente sur cette période</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Revenu par boutique -->
<?php if (!empty($storeRevenue) && count($storeRevenue) > 1): ?>
<div class="card mb-3">
    <div class="card-header-custom">
        <h6><i class="bi bi-shop me-2"></i>Comparaison par Boutique</h6>
    </div>
    <div class="card-body p-3">
        <div class="row g-3">
            <?php foreach ($storeRevenue as $sr): ?>
            <div class="col-md-4">
                <div style="background:var(--body-bg);border-radius:10px;padding:1rem">
                    <div style="font-weight:600;font-size:.9rem;margin-bottom:.4rem"><?= e($sr['store_name']) ?></div>
                    <div style="font-family:Syne,sans-serif;font-weight:700;font-size:1.2rem;color:var(--accent)"><?= formatMoney((float)$sr['revenue']) ?></div>
                    <div style="font-size:.75rem;color:var(--text-muted)"><?= number_format((int)$sr['total_sales']) ?> ventes</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$monthlyJson = json_encode($revenueByMonth);
$salesJson   = json_encode($salesByMonth);
$monthLabels = json_encode($months);
$dailyLabelsJson = json_encode($dailyLabels);
$dailyRevJson    = json_encode(array_map('floatval', $dailyRevenues));
$chartScript = '';
if ($hasData) {
$chartScript = <<<SCRIPT
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const months = $monthLabels;
const revData = $monthlyJson;
const salesData = $salesJson;
const dailyLabels = $dailyLabelsJson;
const dailyRev = $dailyRevJson;

// Monthly chart
const mc = document.getElementById('monthlyChart');
if (mc) new Chart(mc, {
    type: 'bar',
    data: {
        labels: months,
        datasets: [
            {
                label: 'Revenu (FCFA)',
                data: revData,
                backgroundColor: 'rgba(99,102,241,0.15)',
                borderColor: 'rgba(99,102,241,0.8)',
                borderWidth: 2,
                borderRadius: 5,
                yAxisID: 'y',
            },
            {
                label: 'Nb Ventes',
                data: salesData,
                type: 'line',
                borderColor: 'rgba(16,185,129,0.8)',
                backgroundColor: 'rgba(16,185,129,0.1)',
                borderWidth: 2,
                pointRadius: 4,
                tension: 0.4,
                yAxisID: 'y1',
                fill: false,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', labels: { font: { size: 11 } } } },
        scales: {
            y:  { beginAtZero: true, position: 'left', grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font:{size:11}, callback: v => v >= 1000000 ? (v/1000000).toFixed(1)+'M' : v >= 1000 ? (v/1000).toFixed(0)+'k' : v } },
            y1: { beginAtZero: true, position: 'right', grid: { display: false }, ticks: { font:{size:11} } },
            x:  { grid: { display: false }, ticks: { font:{size:11} } }
        }
    }
});

// Daily line chart
const dc = document.getElementById('dailyChart');
if (dc && dailyRev.some(v => v > 0)) new Chart(dc, {
    type: 'line',
    data: {
        labels: dailyLabels.map(d => { const [y,m,day] = d.split('-'); return day+'/'+m; }),
        datasets: [{
            label: 'Revenu (FCFA)',
            data: dailyRev,
            borderColor: 'rgba(16,185,129,0.8)',
            backgroundColor: 'rgba(16,185,129,0.08)',
            borderWidth: 2,
            pointRadius: 3,
            tension: 0.4,
            fill: true,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font:{size:10}, callback: v => v >= 1000000 ? (v/1000000).toFixed(1)+'M' : v >= 1000 ? (v/1000).toFixed(0)+'k' : v } },
            x: { grid: { display: false }, ticks: { font:{size:10} } }
        }
    }
});
</script>
SCRIPT;
}
$extraScript = $chartScript;
require_once __DIR__ . '/layout_bottom.php';
?>
