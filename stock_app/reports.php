<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

require_permission('view_reports');

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$summaryStmt = $conn->prepare(
    'SELECT COUNT(*) AS sales_count, COALESCE(SUM(total_amount), 0) AS total_sales, COALESCE(SUM(paid_amount), 0) AS total_paid
     FROM sales
     WHERE DATE(created_at) BETWEEN ? AND ?'
);
$summaryStmt->bind_param('ss', $dateFrom, $dateTo);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

$topProductsStmt = $conn->prepare(
    'SELECT p.product_name, SUM(si.quantity) AS qty_sold, SUM(si.line_total) AS sales_total
     FROM sale_items si
     INNER JOIN sales s ON s.id = si.sale_id
     INNER JOIN products p ON p.id = si.product_id
     WHERE DATE(s.created_at) BETWEEN ? AND ?
     GROUP BY p.id, p.product_name
     ORDER BY qty_sold DESC, sales_total DESC
     LIMIT 10'
);
$topProductsStmt->bind_param('ss', $dateFrom, $dateTo);
$topProductsStmt->execute();
$topProducts = $topProductsStmt->get_result();
$topProductsStmt->close();

$salesStmt = $conn->prepare(
    'SELECT s.sale_number, s.customer_name, s.total_amount, s.paid_amount, s.created_at, u.full_name
     FROM sales s
     INNER JOIN users u ON u.id = s.user_id
     WHERE DATE(s.created_at) BETWEEN ? AND ?
     ORDER BY s.created_at DESC, s.id DESC
     LIMIT 50'
);
$salesStmt->bind_param('ss', $dateFrom, $dateTo);
$salesStmt->execute();
$sales = $salesStmt->get_result();
$salesStmt->close();

require __DIR__ . '/partials/header.php';
?>
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Rapports de vente</h1>
        <p class="text-muted mb-0">Analyse des ventes et suivi des produits sur une periode donnee.</p>
    </div>
    <div class="d-flex flex-column flex-sm-row gap-2">
        <form class="row g-2" method="get">
            <div class="col-auto">
                <label class="form-label small mb-1" for="date_from">Du</label>
                <input class="form-control" type="date" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1" for="date_to">Au</label>
                <input class="form-control" type="date" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-auto align-self-end">
                <button class="btn btn-primary" type="submit">Filtrer</button>
            </div>
        </form>
        <div class="align-self-end">
            <button class="btn btn-outline-secondary" type="button" onclick="window.print()">Imprimer le rapport</button>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <p class="text-muted mb-2">Nombre de ventes</p>
                <h2 class="fw-bold mb-0"><?= (int) ($summary['sales_count'] ?? 0) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <p class="text-muted mb-2">Chiffre d affaires</p>
                <h2 class="fw-bold mb-0"><?= format_number((float) ($summary['total_sales'] ?? 0)) ?> FCFA</h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <p class="text-muted mb-2">Montant encaisse</p>
                <h2 class="fw-bold mb-0"><?= format_number((float) ($summary['total_paid'] ?? 0)) ?> FCFA</h2>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card stat-card">
            <div class="card-header bg-white border-0 pt-4">
                <h2 class="h5 mb-0">Produits les plus vendus</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Qte</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($topProducts && $topProducts->num_rows > 0): ?>
                                <?php while ($product = $topProducts->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= e($product['product_name']) ?></td>
                                        <td><?= format_number((float) $product['qty_sold']) ?></td>
                                        <td><?= format_number((float) $product['sales_total']) ?> FCFA</td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center text-muted">Aucune donnee sur cette periode.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card stat-card">
            <div class="card-header bg-white border-0 pt-4">
                <h2 class="h5 mb-0">Historique des ventes</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Numero</th>
                                <th>Client</th>
                                <th>Total</th>
                                <th>Paye</th>
                                <th>Date</th>
                                <th>Utilisateur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($sales && $sales->num_rows > 0): ?>
                                <?php while ($sale = $sales->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= e($sale['sale_number']) ?></td>
                                        <td><?= e($sale['customer_name'] ?: 'Client comptoir') ?></td>
                                        <td><?= format_number((float) $sale['total_amount']) ?> FCFA</td>
                                        <td><?= format_number((float) $sale['paid_amount']) ?> FCFA</td>
                                        <td><?= e($sale['created_at']) ?></td>
                                        <td><?= e($sale['full_name']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-muted">Aucune vente sur cette periode.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
