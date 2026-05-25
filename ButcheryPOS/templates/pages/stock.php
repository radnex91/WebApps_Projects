<?php
// ButcheryPOS - Stock Page
$products = $productService->getAllProducts(null, true);
$suppliers = $supplierModel->all(['is_active' => 1], 'name ASC');

// Filters
$filterProduct = (int)($_GET['product'] ?? 0);
$filterType = $_GET['type'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

$movements = $stockService->getMovements(
    $filterProduct ?: null,
    $filterType ?: null,
    $filterDateFrom ?: null,
    $filterDateTo ?: null
);

$movementTypes = ['purchase' => t('purchase'), 'sale' => t('sale'), 'waste' => t('waste'), 'adjustment' => t('adjustment'), 'return' => t('return'), 'breakdown' => t('breakdown')];
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><?= t('stock_management') ?></h4>
        <small class="text-muted"><?= count($movements) ?> <?= t('movements_found') ?></small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#receiveStockModal">
            <i class="bi bi-box-arrow-in-down"></i> <?= t('receive_stock') ?>
        </button>
        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#adjustStockModal">
            <i class="bi bi-arrow-left-right"></i> <?= t('stock_adjustment') ?>
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="d-flex align-items-center gap-3 flex-wrap">
            <input type="hidden" name="page" value="stock">

            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-muted small"><?= t('product') ?>:</label>
                <select name="product" class="form-select form-select-sm" style="width:auto;min-width:180px;">
                    <option value="0"><?= t('all_products') ?></option>
                    <?php foreach ($products as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $filterProduct === (int)$p['id'] ? 'selected' : '' ?>>
                        <?= e($p['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-muted small"><?= t('type') ?>:</label>
                <select name="type" class="form-select form-select-sm" style="width:auto;">
                    <option value=""><?= t('all_types') ?></option>
                    <?php foreach ($movementTypes as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filterType === $key ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-muted small"><?= t('from') ?>:</label>
                <input type="date" name="date_from" class="form-control form-control-sm" style="width:auto;"
                       value="<?= e($filterDateFrom) ?>">
            </div>

            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-muted small"><?= t('to') ?>:</label>
                <input type="date" name="date_to" class="form-control form-control-sm" style="width:auto;"
                       value="<?= e($filterDateTo) ?>">
            </div>

            <button type="submit" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-funnel"></i> <?= t('filter') ?>
            </button>
            <a href="<?= url('?page=stock') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-lg"></i> <?= t('clear') ?>
            </a>
        </form>
    </div>
</div>

<!-- Stock Movements Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($movements)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-archive fs-1"></i>
            <p class="mt-2"><?= t('no_stock_movements') ?></p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= t('date') ?></th>
                        <th><?= t('product') ?></th>
                        <th><?= t('type') ?></th>
                        <th class="text-end"><?= t('quantity') ?></th>
                        <th class="text-end"><?= t('stock_before') ?></th>
                        <th class="text-end"><?= t('stock_after') ?></th>
                        <th class="text-end"><?= t('unit_cost') ?></th>
                        <th><?= t('reason') ?></th>
                        <th><?= t('by') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movements as $mov): ?>
                    <tr>
                        <td><?= format_date($mov['created_at'], 'd/m/Y H:i') ?></td>
                        <td><strong><?= e($mov['product_name']) ?></strong></td>
                        <td>
                            <?php
                            $typeColors = [
                                'purchase' => 'success',
                                'sale' => 'info',
                                'waste' => 'danger',
                                'adjustment' => 'warning',
                                'return' => 'secondary',
                                'breakdown' => 'dark',
                            ];
                            $typeColor = $typeColors[$mov['movement_type']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $typeColor ?>">
                                <?= t($mov['movement_type']) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <?php $qty = (float)$mov['quantity']; ?>
                            <span class="<?= $qty >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $qty >= 0 ? '+' : '' ?><?= $qty ?>
                            </span>
                        </td>
                        <td class="text-end text-muted"><?= (float)$mov['quantity_before'] ?></td>
                        <td class="text-end"><?= (float)$mov['quantity_after'] ?></td>
                        <td class="text-end"><?= money((float)$mov['unit_cost']) ?></td>
                        <td class="text-muted"><?= e($mov['reason'] ?? '-') ?></td>
                        <td class="text-muted"><?= e($mov['created_by_name'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Receive Stock Modal -->
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
                        <label class="form-label"><?= t('product') ?> <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select" required>
                            <option value=""><?= t('select_product') ?></option>
                            <?php foreach ($products as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" data-unit="<?= e($p['unit_abbr'] ?? '') ?>">
                                <?= e($p['name']) ?> (<?= e($p['sku']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label"><?= t('quantity') ?> <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?= t('unit_cost') ?> <span class="text-danger">*</span></label>
                            <input type="number" name="unit_cost" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= t('expiry_date') ?></label>
                        <input type="date" name="expiry_date" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= t('supplier') ?></label>
                        <select name="supplier_id" class="form-select">
                            <option value=""><?= t('select_supplier') ?></option>
                            <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= (int)$sup['id'] ?>"><?= e($sup['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
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

<!-- Stock Adjustment Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= url('?action=adjust_stock') ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-left-right"></i> <?= t('stock_adjustment') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= t('product') ?> <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select" required>
                            <option value=""><?= t('select_product') ?></option>
                            <?php foreach ($products as $p): ?>
                            <option value="<?= (int)$p['id'] ?>">
                                <?= e($p['name']) ?> (<?= e($p['sku']) ?>) &mdash; <?= (float)$p['quantity_in_stock'] ?> <?= e($p['unit_abbr'] ?? '') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= t('adjustment_type') ?> <span class="text-danger">*</span></label>
                        <select name="adjustment_type" class="form-select" required>
                            <option value=""><?= t('select_type') ?></option>
                            <option value="waste"><?= t('waste') ?></option>
                            <option value="adjustment"><?= t('adjustment') ?></option>
                            <option value="return"><?= t('return') ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= t('quantity') ?> <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control" step="0.01" min="0.01" required>
                        <small class="text-muted"><?= t('adjustment_quantity_help') ?></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= t('reason') ?> <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="2" required placeholder="<?= t('adjustment_reason_placeholder') ?>"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-lg"></i> <?= t('submit_adjustment') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>