<?php
// ButcheryPOS - Dashboard Page
use App\Core\Auth;
use App\Core\Permission;

$todaySummary = $saleService->getTodaySummary();
$lowStockProducts = $stockService->getLowStockProducts();
$lowStockCount = count($lowStockProducts);
$expiryAlerts = $expiryService->getActiveAlerts();
$expiryAlertCount = count($expiryAlerts);
$recentSales = $saleService->getSalesHistory(date('Y-m-d'), date('Y-m-d'));
$recentSales = array_slice($recentSales, 0, 10);

// Total stock value
$allProducts = $productService->getAllProducts(null, true);
$totalStockValue = 0;
foreach ($allProducts as $p) {
    $totalStockValue += (float)$p['quantity_in_stock'] * (float)$p['cost_price'];
}
?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-success bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-cash-stack text-success fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1 small"><?= t('todays_sales') ?></h6>
                        <h4 class="mb-0"><?= money((float)$todaySummary['total_revenue']) ?></h4>
                        <small class="text-muted"><?= (int)$todaySummary['total_sales'] ?> <?= t('transactions') ?></small>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0">
                <small>
                    <i class="bi bi-cash text-success"></i> <?= money((float)$todaySummary['cash_total']) ?>
                    &nbsp;
                    <i class="bi bi-credit-card text-info"></i> <?= money((float)$todaySummary['other_total']) ?>
                </small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-exclamation-triangle text-warning fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1 small"><?= t('low_stock') ?></h6>
                        <h4 class="mb-0"><?= $lowStockCount ?></h4>
                        <small class="text-muted"><?= t('products_below_reorder') ?></small>
                    </div>
                </div>
            </div>
            <?php if ($lowStockCount > 0): ?>
            <div class="card-footer bg-transparent border-0 pt-0">
                <a href="<?= url('?page=products&filter=low_stock') ?>" class="small text-decoration-none">
                    <i class="bi bi-arrow-right"></i> <?= t('view_low_stock') ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-danger bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-clock-history text-danger fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1 small"><?= t('expiry_alerts') ?></h6>
                        <h4 class="mb-0"><?= $expiryAlertCount ?></h4>
                        <small class="text-muted"><?= t('batches_near_expiry') ?></small>
                    </div>
                </div>
            </div>
            <?php if ($expiryAlertCount > 0): ?>
            <div class="card-footer bg-transparent border-0 pt-0">
                <a href="<?= url('?page=expiry') ?>" class="small text-decoration-none text-danger">
                    <i class="bi bi-arrow-right"></i> <?= t('view_expiry_alerts') ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (Permission::currentUserCan('stock', 'can_manage')): ?>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-box-seam text-primary fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1 small"><?= t('total_stock_value') ?></h6>
                        <h4 class="mb-0"><?= money($totalStockValue) ?></h4>
                        <small class="text-muted"><?= count($allProducts) ?> <?= t('active_products') ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <h6 class="mb-3"><i class="bi bi-lightning"></i> <?= t('quick_actions') ?></h6>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= url('?page=pos') ?>" class="btn btn-success">
                        <i class="bi bi-cash-register"></i> <?= t('new_sale') ?>
                    </a>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#receiveStockModal">
                        <i class="bi bi-box-arrow-in-down"></i> <?= t('receive_stock') ?>
                    </button>
                    <a href="<?= url('?page=products') ?>" class="btn btn-outline-primary">
                        <i class="bi bi-box-seam"></i> <?= t('manage_products') ?>
                    </a>
                    <a href="<?= url('?page=sales') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-receipt"></i> <?= t('sales_history') ?>
                    </a>
                    <a href="<?= url('?page=expiry') ?>" class="btn btn-outline-danger">
                        <i class="bi bi-clock-history"></i> <?= t('expiry_alerts') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Sales & Low Stock -->
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-receipt"></i> <?= t('recent_sales') ?></h6>
                <a href="<?= url('?page=sales') ?>" class="btn btn-sm btn-outline-secondary">
                    <?= t('view_all') ?> <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentSales)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-receipt fs-1"></i>
                    <p class="mt-2"><?= t('no_sales_today') ?></p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?= t('reference') ?></th>
                                <th><?= t('customer') ?></th>
                                <th><?= t('total') ?></th>
                                <th><?= t('payment') ?></th>
                                <th><?= t('time') ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td><strong><?= e($sale['reference']) ?></strong></td>
                                <td><?= e($sale['customer_name'] ?? t('walk_in')) ?></td>
                                <td><?= money((float)$sale['total_amount']) ?></td>
                                <td>
                                    <?php
                                    $pm = $sale['payment_method'] ?? 'cash';
                                    $pmIcon = $pm === 'cash' ? 'bi-cash' : 'bi-credit-card';
                                    ?>
                                    <i class="bi <?= $pmIcon ?>"></i> <?= t($pm) ?>
                                </td>
                                <td><?= format_date($sale['created_at'], 'H:i') ?></td>
                                <td>
                                    <a href="<?= url('?page=sales&action=view&id=' . $sale['id']) ?>" class="btn btn-sm btn-outline-secondary" title="<?= t('view') ?>">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-exclamation-triangle text-warning"></i> <?= t('low_stock_alerts') ?></h6>
                <?php if ($lowStockCount > 5): ?>
                <a href="<?= url('?page=products&filter=low_stock') ?>" class="btn btn-sm btn-outline-secondary">
                    <?= t('view_all') ?>
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($lowStockProducts)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-check-circle fs-1 text-success"></i>
                    <p class="mt-2"><?= t('all_stock_ok') ?></p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($lowStockProducts, 0, 8) as $product): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= e($product['name']) ?></strong>
                            <br>
                            <small class="text-muted"><?= e($product['category_name'] ?? '') ?></small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-danger">
                                <?= (float)$product['quantity_in_stock'] ?> / <?= (float)$product['reorder_level'] ?> <?= e($product['unit_abbr'] ?? '') ?>
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

<!-- Receive Stock Quick Modal -->
<div class="modal fade" id="receiveStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= url('?action=receive_stock') ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-arrow-in-down"></i> <?= t('receive_stock') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= t('product') ?></label>
                        <select name="product_id" class="form-select" required>
                            <option value=""><?= t('select_product') ?></option>
                            <?php foreach ($allProducts as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label"><?= t('quantity') ?></label>
                            <input type="number" name="quantity" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?= t('unit_cost') ?></label>
                            <input type="number" name="unit_cost" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label"><?= t('expiry_date') ?></label>
                        <input type="date" name="expiry_date" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= t('batch_reference') ?></label>
                        <input type="text" name="batch_reference" class="form-control" placeholder="<?= t('auto_if_blank') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> <?= t('receive_stock') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>