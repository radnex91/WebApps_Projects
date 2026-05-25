<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requirePermission('dashboard');

$pageTitle = 'Dashboard';
$saleModel = new Sale();
$productModel = new Product();
$storeId = currentStoreId();
$userId  = (int)$_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';
$canViewAll = in_array($userRole, ['admin', 'manager']) || hasPermission('reports');

// Filtre utilisateur : caissiers voient leurs propres stats, managers/admins peuvent filtrer
$filterUserId = null;
if ($canViewAll) {
    $filterUserId = (int)($_GET['user_id'] ?? 0);
} else {
    $filterUserId = $userId;
}

$db = Database::getInstance();

// Stats du jour
$todayWhere = "s.store_id = ? AND s.status = 'completed' AND DATE(s.sale_date) = ?";
$todayParams = [$storeId, date('Y-m-d')];
if ($filterUserId) { $todayWhere .= " AND s.user_id = ?"; $todayParams[] = $filterUserId; }
$todayStats = $db->prepare("SELECT COUNT(*) as total_sales, SUM(total_amount) as revenue, AVG(total_amount) as avg_sale FROM sales s WHERE $todayWhere");
$todayStats->execute($todayParams);
$todayStats = $todayStats->fetch() ?: ['total_sales' => 0, 'revenue' => 0, 'avg_sale' => 0];

// Stats hier
$yesterdayWhere = "s.store_id = ? AND s.status = 'completed' AND DATE(s.sale_date) = ?";
$yesterdayParams = [$storeId, date('Y-m-d', strtotime('-1 day'))];
if ($filterUserId) { $yesterdayWhere .= " AND s.user_id = ?"; $yesterdayParams[] = $filterUserId; }
$yesterdayStats = $db->prepare("SELECT SUM(total_amount) as revenue FROM sales s WHERE $yesterdayWhere");
$yesterdayStats->execute($yesterdayParams);
$yesterdayStats = $yesterdayStats->fetch() ?: ['revenue' => 0];

// Revenu mensuel
$monthWhere = "s.store_id = ? AND YEAR(s.sale_date) = ? AND s.status = 'completed'";
$monthParams = [$storeId, date('Y')];
if ($filterUserId) { $monthWhere .= " AND s.user_id = ?"; $monthParams[] = $filterUserId; }
$monthlyData = $db->prepare("SELECT MONTH(sale_date) as month, SUM(total_amount) as revenue, COUNT(*) as count FROM sales s WHERE $monthWhere GROUP BY MONTH(sale_date) ORDER BY month");
$monthlyData->execute($monthParams);
$monthlyData = $monthlyData->fetchAll();

// Top produits
$topWhere = "s.store_id = ? AND s.status = 'completed'";
$topParams = [$storeId];
if ($filterUserId) { $topWhere .= " AND s.user_id = ?"; $topParams[] = $filterUserId; }
$topParams[] = 5;
$topStmt = $db->prepare(
    "SELECT si.product_id, si.product_name, SUM(si.quantity) as total_qty,
     SUM(si.total_price) as total_revenue
     FROM sale_items si JOIN sales s ON si.sale_id = s.id
     WHERE $topWhere GROUP BY si.product_id, si.product_name ORDER BY total_revenue DESC LIMIT ?"
);
$topStmt->execute($topParams);
$topProducts = $topStmt->fetchAll();

// Ventes récentes
$recentWhere = "s.store_id = ?";
$recentParams = [$storeId];
if ($filterUserId) { $recentWhere .= " AND s.user_id = ?"; $recentParams[] = $filterUserId; }
$recentParams[] = 10;
$recentStmt = $db->prepare(
    "SELECT s.*, u.name as cashier_name, c.name as customer_name
     FROM sales s JOIN users u ON s.user_id = u.id LEFT JOIN customers c ON s.customer_id = c.id
     WHERE $recentWhere ORDER BY s.sale_date DESC LIMIT ?"
);
$recentStmt->execute($recentParams);
$recentSales = $recentStmt->fetchAll();

// Stock faible
$lowStock = $productModel->getLowStock($storeId);

// Revenu par boutique (seulement si vue globale)
$storeRevenue = [];
if (!$filterUserId) {
    $storeRevenue = $saleModel->getRevenueByStore();
}

// Liste des utilisateurs pour le filtre
$users = [];
if ($canViewAll) {
    $userStmt = $db->prepare("SELECT id, name FROM users WHERE store_id = ? AND is_active = 1 ORDER BY name");
    $userStmt->execute([$storeId]);
    $users = $userStmt->fetchAll();
}

// Préparer données graphiques
$months = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
$revenueByMonth = array_fill(0, 12, 0);
foreach ($monthlyData as $m) {
    $revenueByMonth[(int)$m['month'] - 1] = (float)$m['revenue'];
}

// Variation vs hier
$revVar = ($yesterdayStats['revenue'] ?? 0) > 0
    ? round(((($todayStats['revenue'] ?? 0) - $yesterdayStats['revenue']) / $yesterdayStats['revenue']) * 100, 1)
    : 0;

require_once __DIR__ . '/layout_top.php';
?>

<!-- Filtre utilisateur -->
<?php if ($canViewAll): ?>
<div class="card mb-3" style="border-radius:12px">
    <div class="card-body py-2 px-3">
        <form method="GET" class="d-flex align-items-center gap-2">
            <i class="bi bi-person-lines-fill" style="color:var(--accent)"></i>
            <span style="font-size:.82rem;font-weight:600">Vue par caissier :</span>
            <select name="user_id" class="form-select form-select-sm" style="border-radius:8px;width:auto" onchange="this.form.submit()">
                <option value="">Tous les caissiers (global)</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $filterUserId == $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($filterUserId): ?>
            <a href="?" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:.72rem">Vue globale</a>
            <?php endif; ?>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="stat-card accent">
            <div class="stat-icon accent"><i class="bi bi-currency-dollar"></i></div>
            <div class="stat-value"><?= formatMoney((float)$todayStats['revenue']) ?></div>
            <div class="stat-label">Ventes aujourd'hui</div>
            <div class="stat-change <?= $revVar >= 0 ? 'up' : 'down' ?>">
                <i class="bi bi-arrow-<?= $revVar >= 0 ? 'up' : 'down' ?>"></i>
                <?= abs($revVar) ?>% vs hier
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card success">
            <div class="stat-icon success"><i class="bi bi-bag-check"></i></div>
            <div class="stat-value"><?= number_format((int)$todayStats['total_sales']) ?></div>
            <div class="stat-label">Transactions du jour</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card warning">
            <div class="stat-icon warning"><i class="bi bi-graph-up"></i></div>
            <div class="stat-value"><?= formatMoney(array_sum(array_column($monthlyData, 'revenue'))) ?></div>
            <div class="stat-label">Revenu ce mois</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card danger">
            <div class="stat-icon danger"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-value"><?= count($lowStock) ?></div>
            <div class="stat-label">Alertes stock faible</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Graphique Revenu -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header-custom">
                <h6><i class="bi bi-graph-up me-2 text-primary"></i>Revenu Mensuel <?= date('Y') ?></h6>
            </div>
            <div class="card-body p-3">
                <canvas id="revenueChart" height="260"></canvas>
            </div>
        </div>
    </div>

    <!-- Revenu par boutique (vue globale uniquement) -->
    <?php if (!$filterUserId && !empty($storeRevenue)): ?>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header-custom">
                <h6><i class="bi bi-shop me-2 text-success"></i>Revenu par Boutique</h6>
            </div>
            <div class="card-body p-3">
                <canvas id="storeChart" height="260"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row g-3">
    <!-- Top Produits -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header-custom">
                <h6><i class="bi bi-star me-2 text-warning"></i>Top 5 Produits</h6>
                <a href="<?= BASE_URL ?>/views/reports.php" class="btn btn-sm btn-outline-secondary" style="font-size:.75rem">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">Produit</th>
                            <th class="text-end">Qté</th>
                            <th class="text-end pe-3">Revenu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $i => $p): ?>
                        <tr>
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge rounded-pill" style="background:rgba(99,102,241,0.1);color:#6366F1;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:.7rem"><?= $i+1 ?></span>
                                    <span style="font-size:.85rem"><?= e($p['product_name']) ?></span>
                                </div>
                            </td>
                            <td class="text-end" style="font-size:.85rem"><?= number_format((float)$p['total_qty']) ?></td>
                            <td class="text-end pe-3" style="font-size:.85rem;font-weight:500"><?= formatMoney((float)$p['total_revenue']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topProducts)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3" style="font-size:.85rem">Aucune vente pour l'instant</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Alertes Stock -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header-custom">
                <h6><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Alertes Stock Faible</h6>
                <a href="<?= BASE_URL ?>/views/stock.php" class="btn btn-sm btn-outline-danger" style="font-size:.75rem">Gérer</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($lowStock)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-check-circle text-success" style="font-size:2rem"></i>
                    <p class="mt-2 mb-0" style="font-size:.85rem">Tous les stocks sont corrects</p>
                </div>
                <?php else: ?>
                <div style="max-height: 280px; overflow-y: auto;">
                    <?php foreach (array_slice($lowStock, 0, 8) as $item): ?>
                    <div class="d-flex align-items-center justify-content-between px-3 py-2" style="border-bottom:1px solid var(--border)">
                        <div style="min-width:0">
                            <div style="font-size:.85rem;font-weight:500" class="text-truncate"><?= e($item['name']) ?></div>
                            <div style="font-size:.72rem;color:var(--text-muted)"><?= e($item['warehouse_name'] ?? 'N/A') ?></div>
                        </div>
                        <div>
                            <span class="badge <?= (float)$item['stock_qty'] <= 0 ? 'bg-danger' : 'bg-warning text-dark' ?>" style="font-size:.72rem">
                                <?= number_format((float)$item['stock_qty']) ?> <?= e($item['unit'] ?? '') ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Ventes récentes -->
<div class="row g-3 mt-0">
    <div class="col-12">
        <div class="card mt-3">
            <div class="card-header-custom">
                <h6><i class="bi bi-receipt me-2"></i>Ventes Récentes</h6>
                <a href="<?= BASE_URL ?>/views/sales.php" class="btn btn-sm btn-outline-secondary" style="font-size:.75rem">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Facture</th>
                                <th>Date</th>
                                <th>Caissier</th>
                                <th>Client</th>
                                <th>Paiement</th>
                                <th class="text-end">Montant</th>
                                <th class="text-center pe-3">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($recentSales, 0, 5) as $sale): ?>
                            <tr>
                                <td class="ps-3">
                                    <a href="<?= BASE_URL ?>/views/invoice.php?id=<?= $sale['id'] ?>" style="font-size:.85rem;text-decoration:none;color:var(--accent);font-weight:500">
                                        <?= e($sale['invoice_number']) ?>
                                    </a>
                                </td>
                                <td style="font-size:.82rem;color:var(--text-muted)"><?= formatDate($sale['sale_date']) ?></td>
                                <td style="font-size:.82rem"><?= e($sale['cashier_name'] ?? '') ?></td>
                                <td style="font-size:.82rem"><?= e($sale['customer_name'] ?? 'Client anonyme') ?></td>
                                <td>
                                    <span class="badge" style="font-size:.7rem;background:rgba(99,102,241,0.1);color:#6366F1">
                                        <?= e(ucfirst(str_replace('_', ' ', $sale['payment_method']))) ?>
                                    </span>
                                </td>
                                <td class="text-end" style="font-size:.85rem;font-weight:600"><?= formatMoney((float)$sale['total_amount']) ?></td>
                                <td class="text-center pe-3">
                                    <span class="badge <?= $sale['status'] === 'completed' ? 'bg-success' : 'bg-secondary' ?>" style="font-size:.7rem">
                                        <?= ucfirst($sale['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentSales)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-3" style="font-size:.85rem">Aucune vente enregistrée</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extraScript = <<<SCRIPT
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const revenueData = [REVENUE_BY_MONTH];
const months = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];

// Revenu Chart
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: months,
        datasets: [{
            label: 'Revenu (FCFA)',
            data: revenueData,
            backgroundColor: 'rgba(99,102,241,0.15)',
            borderColor: 'rgba(99,102,241,0.8)',
            borderWidth: 2,
            borderRadius: 6,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: {
                    font: { size: 11 },
                    callback: v => v >= 1000000 ? (v/1000000).toFixed(1)+'M' : v >= 1000 ? (v/1000).toFixed(0)+'k' : v
                }
            },
            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
    }
});

// Store Chart (only if global view)
const storeEl = document.getElementById('storeChart');
if (storeEl) {
const storeLabels = [STORE_LABELS];
const storeRevs   = [STORE_REVS];
new Chart(storeEl, {
    type: 'doughnut',
    data: {
        labels: storeLabels,
        datasets: [{
            data: storeRevs,
            backgroundColor: ['rgba(99,102,241,0.7)', 'rgba(16,185,129,0.7)', 'rgba(245,158,11,0.7)', 'rgba(239,68,68,0.7)'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 12 }, padding: 12 } } },
        cutout: '65%'
    }
});
}
</script>
SCRIPT;

$extraScript = str_replace(
    ['REVENUE_BY_MONTH', 'STORE_LABELS', 'STORE_REVS'],
    [
        implode(',', $revenueByMonth),
        implode(',', array_map(fn($s) => '"' . e($s['store_name']) . '"', $storeRevenue)),
        implode(',', array_column($storeRevenue, 'revenue'))
    ],
    $extraScript
);

require_once __DIR__ . '/layout_bottom.php';
?>
