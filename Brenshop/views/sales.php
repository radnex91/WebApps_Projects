<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requirePermission('sales');

$pageTitle = 'Historique des Ventes';
$saleModel = new Sale();
$storeId   = currentStoreId();
$userId    = (int)($_SESSION['user_id']);
$userRole  = $_SESSION['user_role'] ?? '';
$isGlobal  = hasPermission('sales'); // managers/admins can see global
$canViewAll = in_array($userRole, ['admin', 'manager']) || hasPermission('reports');

// Si caissier sans droit rapports, forcer ses propres ventes
$filterUserId = null;
if ($canViewAll) {
    $filterUserId = (int)($_GET['user_id'] ?? 0);
} else {
    $filterUserId = $userId;
}

$page     = max(1, (int)($_GET['page'] ?? 1));
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo   = sanitize($_GET['date_to']   ?? '');
$search   = sanitize($_GET['search']    ?? '');

// Construire la requête
$db = Database::getInstance();
$where  = "s.store_id = ?";
$params = [$storeId];
if ($dateFrom) { $where .= " AND DATE(s.sale_date) >= ?"; $params[] = $dateFrom; }
if ($dateTo)   { $where .= " AND DATE(s.sale_date) <= ?"; $params[] = $dateTo; }
if ($search)   { $where .= " AND (s.invoice_number LIKE ? OR c.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filterUserId) { $where .= " AND s.user_id = ?"; $params[] = $filterUserId; }

$countStmt = $db->prepare("SELECT COUNT(*) FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE $where");
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$pagination = paginate($total, $page);
$offset     = $pagination['offset'];

// Ventes paginées
$paramsPaged = array_merge($params, [ITEMS_PER_PAGE, $offset]);
$stmt = $db->prepare(
    "SELECT s.*, u.name as cashier_name, c.name as customer_name, w.name as warehouse_name
     FROM sales s
     JOIN users u ON s.user_id = u.id
     JOIN warehouses w ON s.warehouse_id = w.id
     LEFT JOIN customers c ON s.customer_id = c.id
     WHERE $where ORDER BY s.sale_date DESC LIMIT ? OFFSET ?"
);
$stmt->execute($paramsPaged);
$sales = $stmt->fetchAll();

// Stats résumé période
$statsWhere = "s.store_id = ? AND s.status='completed'";
$statsParams = [$storeId];
if ($filterUserId) { $statsWhere .= " AND s.user_id = ?"; $statsParams[] = $filterUserId; }
if ($dateFrom) { $statsWhere .= " AND DATE(s.sale_date) >= ?"; $statsParams[] = $dateFrom; }
if ($dateTo)   { $statsWhere .= " AND DATE(s.sale_date) <= ?"; $statsParams[] = $dateTo; }
$statsStmt = $db->prepare(
    "SELECT COUNT(*) as cnt, SUM(total_amount) as revenue, SUM(total_amount - discount_amount - tax_amount) as profit
     FROM sales s WHERE $statsWhere"
);
$statsStmt->execute($statsParams);
$stats = $statsStmt->fetch();

// Liste des caissiers pour le filtre
$cashiers = [];
if ($canViewAll) {
    $cashierStmt = $db->prepare("SELECT id, name FROM users WHERE store_id = ? AND is_active = 1 ORDER BY name");
    $cashierStmt->execute([$storeId]);
    $cashiers = $cashierStmt->fetchAll();
}

require_once __DIR__ . '/layout_top.php';
?>

<!-- Filtres -->
<div class="card mb-3">
    <div class="card-body py-2 px-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-1" style="font-size:.75rem;font-weight:600"><i class="bi bi-search me-1"></i>Recherche</label>
                <input type="text" name="search" value="<?= e($search) ?>" class="form-control form-control-sm" placeholder="N° facture, client..." style="border-radius:8px;width:200px">
            </div>
            <div class="col-auto">
                <label class="form-label mb-1" style="font-size:.75rem;font-weight:600"><i class="bi bi-calendar me-1"></i>Du</label>
                <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="form-control form-control-sm" style="border-radius:8px">
            </div>
            <div class="col-auto">
                <label class="form-label mb-1" style="font-size:.75rem;font-weight:600"><i class="bi bi-calendar me-1"></i>Au</label>
                <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="form-control form-control-sm" style="border-radius:8px">
            </div>
            <?php if ($canViewAll): ?>
            <div class="col-auto">
                <label class="form-label mb-1" style="font-size:.75rem;font-weight:600"><i class="bi bi-person me-1"></i>Caissier</label>
                <select name="user_id" class="form-select form-select-sm" style="border-radius:8px">
                    <option value="">Tous</option>
                    <?php foreach ($cashiers as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filterUserId == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary" style="border-radius:8px"><i class="bi bi-funnel me-1"></i>Filtrer</button>
                <a href="?" class="btn btn-sm btn-outline-secondary" style="border-radius:8px"><i class="bi bi-arrow-counterclockwise me-1"></i>Réinitialiser</a>
            </div>
            <div class="col-auto ms-auto text-end">
                <!-- Quick date filters -->
                <div class="btn-group btn-group-sm">
                    <a href="?date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?><?= $filterUserId ? '&user_id='.$filterUserId : '' ?>" class="btn btn-outline-secondary" style="font-size:.72rem"><i class="bi bi-calendar-event me-1"></i>Aujourd'hui</a>
                    <a href="?date_from=<?= date('Y-m-d', strtotime('monday this week')) ?>&date_to=<?= date('Y-m-d') ?><?= $filterUserId ? '&user_id='.$filterUserId : '' ?>" class="btn btn-outline-secondary" style="font-size:.72rem"><i class="bi bi-calendar-week me-1"></i>Cette semaine</a>
                    <a href="?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-d') ?><?= $filterUserId ? '&user_id='.$filterUserId : '' ?>" class="btn btn-outline-secondary" style="font-size:.72rem"><i class="bi bi-calendar-month me-1"></i>Ce mois</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Stats période -->
<div class="row g-3 mb-3">
    <div class="col-4">
        <div class="stat-card accent">
            <div class="stat-icon accent"><i class="bi bi-receipt"></i></div>
            <div class="stat-value"><?= number_format((int)($stats['cnt'] ?? 0)) ?></div>
            <div class="stat-label">Ventes sur période<?= $filterUserId ? ' (filtré)' : '' ?></div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-card success">
            <div class="stat-icon success"><i class="bi bi-currency-dollar"></i></div>
            <div class="stat-value" style="font-size:1.1rem"><?= formatMoney((float)($stats['revenue'] ?? 0)) ?></div>
            <div class="stat-label">Chiffre d'affaires</div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-card warning">
            <div class="stat-icon warning"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="stat-value" style="font-size:1.1rem"><?= formatMoney((float)($stats['profit'] ?? 0)) ?></div>
            <div class="stat-label">Bénéfice brut</div>
        </div>
    </div>
</div>

<!-- Table ventes -->
<div class="card">
    <div class="card-header-custom">
        <h6><i class="bi bi-receipt me-2"></i>Ventes (<?= number_format($total) ?>)<?php
            if ($filterUserId) {
                $filterName = '';
                foreach ($cashiers as $c) { if ($c['id'] == $filterUserId) { $filterName = $c['name']; break; } }
                echo ' — <span style="color:var(--accent2)">' . e($filterName) . '</span>';
            }
        ?></h6>
        <small class="text-muted">Page <?= $pagination['page'] ?>/<?= $pagination['total_pages'] ?></small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3"><i class="bi bi-file-earmark-text me-1"></i>Facture</th>
                        <th><i class="bi bi-calendar me-1"></i>Date & Heure</th>
                        <th><i class="bi bi-person me-1"></i>Caissier</th>
                        <th><i class="bi bi-building me-1"></i>Magasin</th>
                        <th><i class="bi bi-people me-1"></i>Client</th>
                        <th><i class="bi bi-credit-card me-1"></i>Paiement</th>
                        <th class="text-end"><i class="bi bi-currency-dollar me-1"></i>Total</th>
                        <th class="text-center"><i class="bi bi-flag me-1"></i>Statut</th>
                        <th class="text-center pe-3"><i class="bi bi-gear me-1"></i>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $sale): ?>
                    <?php
                    $methodLabels = ['cash' => '<i class="bi bi-cash me-1"></i>Espèces', 'mobile_money' => '<i class="bi bi-phone me-1"></i>Mobile Money', 'orange_money' => '<i class="bi bi-phone me-1"></i>Orange Money', 'momo' => '<i class="bi bi-phone me-1"></i>MoMo', 'card' => '<i class="bi bi-credit-card me-1"></i>Carte', 'credit' => '<i class="bi bi-credit-card me-1"></i>Crédit', 'mixed' => 'Mixte'];
                    $methodColors = ['cash' => '#10B981', 'mobile_money' => '#6366F1', 'card' => '#F59E0B', 'credit' => '#EF4444', 'mixed' => '#8B5CF6'];
                    $method = $sale['payment_method'];
                    ?>
                    <tr>
                        <td class="ps-3">
                            <a href="<?= BASE_URL ?>/views/invoice.php?id=<?= $sale['id'] ?>" target="_blank" style="font-family:monospace;font-size:.82rem;font-weight:600;color:var(--accent);text-decoration:none">
                                <?= e($sale['invoice_number']) ?>
                            </a>
                        </td>
                        <td style="font-size:.8rem">
                            <div><?= date('d/m/Y', strtotime($sale['sale_date'])) ?></div>
                            <div style="color:var(--text-muted)"><?= date('H:i', strtotime($sale['sale_date'])) ?></div>
                        </td>
                        <td style="font-size:.82rem"><?= e($sale['cashier_name']) ?></td>
                        <td style="font-size:.78rem;color:var(--text-muted)"><?= e($sale['warehouse_name']) ?></td>
                        <td style="font-size:.82rem"><?= e($sale['customer_name'] ?? 'Anonyme') ?></td>
                        <td>
                            <span class="badge" style="background:<?= isset($methodColors[$method]) ? $methodColors[$method] : '#64748B' ?>22;color:<?= $methodColors[$method] ?? '#64748B' ?>;font-size:.7rem">
                                <?= $methodLabels[$method] ?? ucfirst($method) ?>
                            </span>
                        </td>
                        <td class="text-end" style="font-size:.875rem;font-weight:600"><?= formatMoney((float)$sale['total_amount']) ?></td>
                        <td class="text-center">
                            <?php
                            $sc = ['completed' => 'success', 'pending' => 'warning', 'cancelled' => 'secondary', 'refunded' => 'info'];
                            $sl = ['completed' => '<i class="bi bi-check-circle me-1"></i>Complété', 'pending' => '<i class="bi bi-clock me-1"></i>En attente', 'cancelled' => '<i class="bi bi-x-circle me-1"></i>Annulé', 'refunded' => '<i class="bi bi-arrow-counterclockwise me-1"></i>Remboursé'];
                            ?>
                            <span class="badge bg-<?= $sc[$sale['status']] ?? 'secondary' ?>" style="font-size:.7rem">
                                <?= $sl[$sale['status']] ?? $sale['status'] ?>
                            </span>
                        </td>
                        <td class="text-center pe-3">
                            <div class="d-flex gap-1 justify-content-center">
                                <a href="<?= BASE_URL ?>/views/invoice.php?id=<?= $sale['id'] ?>" target="_blank"
                                   class="btn btn-sm btn-outline-primary" style="padding:.25rem .5rem;font-size:.72rem;border-radius:6px" title="Voir facture">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/views/invoice.php?id=<?= $sale['id'] ?>&print=1" target="_blank"
                                   class="btn btn-sm btn-outline-secondary" style="padding:.25rem .5rem;font-size:.72rem;border-radius:6px" title="Imprimer">
                                    <i class="bi bi-printer"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($sales)): ?>
                    <tr><td colspan="9" class="text-center py-4 text-muted"><i class="bi bi-receipt d-block" style="font-size:2rem;opacity:.2"></i>Aucune vente trouvée</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer py-2">
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php if ($pagination['has_prev']): ?>
                <li class="page-item"><a class="page-link" href="?page=<?= $pagination['page']-1 ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&search=<?= urlencode($search) ?><?= $filterUserId ? '&user_id='.$filterUserId : '' ?>">&lsaquo;</a></li>
                <?php endif; ?>
                <?php for ($i = max(1, $pagination['page']-2); $i <= min($pagination['total_pages'], $pagination['page']+2); $i++): ?>
                <li class="page-item <?= $i == $pagination['page'] ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&search=<?= urlencode($search) ?><?= $filterUserId ? '&user_id='.$filterUserId : '' ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($pagination['has_next']): ?>
                <li class="page-item"><a class="page-link" href="?page=<?= $pagination['page']+1 ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&search=<?= urlencode($search) ?><?= $filterUserId ? '&user_id='.$filterUserId : '' ?>">&rsaquo;</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/layout_bottom.php'; ?>
