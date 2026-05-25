<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

require_permission('view_dashboard');

$stats = [
    'products' => 0,
    'categories' => 0,
    'users' => 0,
    'stock_value' => 0.0,
    'low_stock' => 0,
    'sales_today' => 0.0,
];

$queries = [
    'products' => 'SELECT COUNT(*) AS total FROM products',
    'categories' => 'SELECT COUNT(*) AS total FROM categories',
    'users' => 'SELECT COUNT(*) AS total FROM users WHERE is_active = 1',
    'stock_value' => 'SELECT COALESCE(SUM(current_stock * unit_price), 0) AS total FROM products',
    'low_stock' => 'SELECT COUNT(*) AS total FROM products WHERE current_stock <= min_stock',
    'sales_today' => 'SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE DATE(created_at) = CURDATE()',
];

foreach ($queries as $key => $sql) {
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $stats[$key] = (float) $row['total'];
    }
}

$latestMovements = $conn->query(
    "SELECT sm.created_at, sm.type, sm.quantity, p.product_name, u.full_name
     FROM stock_movements sm
     INNER JOIN products p ON p.id = sm.product_id
     INNER JOIN users u ON u.id = sm.user_id
     ORDER BY sm.created_at DESC
     LIMIT 8"
);

$criticalProducts = $conn->query(
    "SELECT product_name, sku, current_stock, min_stock
     FROM products
     WHERE current_stock <= min_stock
     ORDER BY current_stock ASC, product_name ASC
     LIMIT 8"
);

require __DIR__ . '/partials/header.php';
?>
<div class="card hero-card shadow-lg mb-4">
    <div class="card-body p-4 p-lg-5">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge badge-soft rounded-pill px-3 py-2 mb-3">Tableau de bord</span>
                <h1 class="display-6 fw-bold">Pilotez vos stocks, vos sorties et vos utilisateurs en un seul endroit.</h1>
                <p class="mb-0 opacity-75">Cette application est prevue pour PHP/MySQL avec XAMPP et permet de suivre les entrees, sorties et alertes de stock faible.</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <p class="text-muted mb-2">Produits</p>
                <h2 class="fw-bold mb-0"><?= (int) $stats['products'] ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <p class="text-muted mb-2">Categories</p>
                <h2 class="fw-bold mb-0"><?= (int) $stats['categories'] ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <p class="text-muted mb-2">Utilisateurs actifs</p>
                <h2 class="fw-bold mb-0"><?= (int) $stats['users'] ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <p class="text-muted mb-2">Valeur du stock</p>
                <h2 class="fw-bold mb-0"><?= format_number($stats['stock_value']) ?> FCFA</h2>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <p class="text-muted mb-2">Ventes du jour</p>
                <h2 class="fw-bold mb-0"><?= format_number($stats['sales_today']) ?> FCFA</h2>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card stat-card">
            <div class="card-header bg-white border-0 pt-4">
                <h3 class="h5 mb-0">Derniers mouvements</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Produit</th>
                                <th>Type</th>
                                <th>Quantite</th>
                                <th>Utilisateur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($latestMovements && $latestMovements->num_rows > 0): ?>
                                <?php while ($movement = $latestMovements->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= e($movement['created_at']) ?></td>
                                        <td><?= e($movement['product_name']) ?></td>
                                        <td><span class="badge text-bg-secondary"><?= e($movement['type']) ?></span></td>
                                        <td><?= format_number((float) $movement['quantity']) ?></td>
                                        <td><?= e($movement['full_name']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted">Aucun mouvement pour le moment.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card stat-card">
            <div class="card-header bg-white border-0 pt-4">
                <h3 class="h5 mb-0">Stock faible</h3>
            </div>
            <div class="card-body">
                <?php if ($criticalProducts && $criticalProducts->num_rows > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while ($product = $criticalProducts->fetch_assoc()): ?>
                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold"><?= e($product['product_name']) ?></div>
                                    <div class="small text-muted"><?= e($product['sku']) ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-danger"><?= format_number((float) $product['current_stock']) ?></div>
                                    <div class="small text-muted">Min: <?= format_number((float) $product['min_stock']) ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Aucune alerte de stock faible.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
