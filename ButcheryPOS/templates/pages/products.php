<?php
// ButcheryPOS - Products Page
$categories = $productService->getAllCategories();
$units = $productService->getAllUnits();
$filterCategory = (int)($_GET['category'] ?? 0);
$products = $productService->getAllProducts($filterCategory ?: null, true);

// Build suppliers list for stock receive modal
$suppliers = $supplierModel->all(['is_active' => 1], 'name ASC');

// Check if editing
$editProduct = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editProduct = $productService->getProduct((int)$_GET['id']);
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><?= t('products') ?></h4>
        <small class="text-muted"><?= count($products) ?> <?= t('products_found') ?></small>
    </div>
    <div>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#productModal" onclick="clearProductForm()">
            <i class="bi bi-plus-lg"></i> <?= t('add_product') ?>
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="d-flex align-items-center gap-3 flex-wrap">
            <input type="hidden" name="page" value="products">
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-muted small"><?= t('filter_by_category') ?>:</label>
                <select name="category" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="0"><?= t('all_categories') ?></option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= $filterCategory === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($products)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-box-seam fs-1"></i>
            <p class="mt-2"><?= t('no_products_found') ?></p>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#productModal" onclick="clearProductForm()">
                <i class="bi bi-plus-lg"></i> <?= t('add_product') ?>
            </button>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= t('name') ?></th>
                        <th><?= t('sku') ?></th>
                        <th><?= t('category') ?></th>
                        <th class="text-end"><?= t('cost_price') ?></th>
                        <th class="text-end"><?= t('sale_price') ?></th>
                        <th class="text-end"><?= t('stock') ?></th>
                        <th class="text-center"><?= t('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <strong><?= e($product['name']) ?></strong>
                            <?php if (!$product['is_active']): ?>
                            <span class="badge bg-secondary"><?= t('inactive') ?></span>
                            <?php endif; ?>
                            <?php if (!empty($product['is_carcass'])): ?>
                            <span class="badge bg-dark"><?= t('is_carcass') ?></span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= e($product['sku']) ?></code></td>
                        <td><?= e($product['category_name'] ?? '-') ?></td>
                        <td class="text-end"><?= money((float)$product['cost_price']) ?></td>
                        <td class="text-end"><?= money((float)$product['sale_price']) ?></td>
                        <td class="text-end">
                            <?php
                            $stock = (float)$product['quantity_in_stock'];
                            $reorder = (float)$product['reorder_level'];
                            $stockClass = $stock <= 0 ? 'text-danger fw-bold' : ($stock <= $reorder ? 'text-warning fw-bold' : '');
                            ?>
                            <span class="<?= $stockClass ?>">
                                <?= $stock ?> <?= e($product['unit_abbr'] ?? '') ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary" title="<?= t('edit') ?>"
                                        onclick='loadProductForEdit(<?= json_encode($product) ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" title="<?= t('delete') ?>"
                                        onclick="confirmDeleteProduct(<?= (int)$product['id'] ?>, '<?= e($product['name']) ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="productForm" method="POST" action="<?= url('?action=save_product') ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="product_id" id="productId" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="productModalTitle">
                        <i class="bi bi-plus-circle"></i> <?= t('add_product') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= t('product_name') ?> <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="productName" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= t('sku') ?></label>
                            <input type="text" name="sku" id="productSku" class="form-control" placeholder="<?= t('auto_generated') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= t('category') ?> <span class="text-danger">*</span></label>
                            <select name="category_id" id="productCategory" class="form-select" required>
                                <option value=""><?= t('select_category') ?></option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label"><?= t('unit') ?> <span class="text-danger">*</span></label>
                            <select name="unit_id" id="productUnit" class="form-select" required>
                                <option value=""><?= t('select_unit') ?></option>
                                <?php foreach ($units as $unit): ?>
                                <option value="<?= (int)$unit['id'] ?>"><?= e($unit['name']) ?> (<?= e($unit['abbreviation']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= t('cost_price') ?> <span class="text-danger">*</span></label>
                            <input type="number" name="cost_price" id="productCostPrice" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= t('sale_price') ?> <span class="text-danger">*</span></label>
                            <input type="number" name="sale_price" id="productSalePrice" class="form-control" step="0.01" min="0" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label"><?= t('reorder_level') ?></label>
                            <input type="number" name="reorder_level" id="productReorderLevel" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= t('default_shelf_life_days') ?></label>
                            <input type="number" name="default_shelf_life_days" id="productShelfLife" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input type="hidden" name="track_expiry" value="0">
                                <input class="form-check-input" type="checkbox" name="track_expiry" id="productTrackExpiry" value="1">
                                <label class="form-check-label" for="productTrackExpiry"><?= t('track_expiry') ?></label>
                            </div>
                        </div>

                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_carcass" value="0">
                                <input class="form-check-input" type="checkbox" name="is_carcass" id="productIsCarcass" value="1">
                                <label class="form-check-label" for="productIsCarcass"><?= t('is_carcass') ?></label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> <?= t('save') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteProductModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" action="<?= url('?action=delete_product') ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="product_id" id="deleteProductId" value="">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger"><i class="bi bi-trash"></i> <?= t('delete_product') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><?= t('confirm_delete_product') ?></p>
                    <strong id="deleteProductName"></strong>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash"></i> <?= t('delete') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function clearProductForm() {
    document.getElementById('productId').value = '';
    document.getElementById('productForm').reset();
    document.getElementById('productModalTitle').innerHTML =
        '<i class="bi bi-plus-circle"></i> <?= e(t('add_product')) ?>';
    document.getElementById('productForm').action = '<?= url("?action=save_product") ?>';
}

function loadProductForEdit(product) {
    document.getElementById('productId').value = product.id;
    document.getElementById('productName').value = product.name;
    document.getElementById('productSku').value = product.sku;
    document.getElementById('productCategory').value = product.category_id || '';
    document.getElementById('productUnit').value = product.unit_id || '';
    document.getElementById('productCostPrice').value = product.cost_price;
    document.getElementById('productSalePrice').value = product.sale_price;
    document.getElementById('productReorderLevel').value = product.reorder_level;
    document.getElementById('productShelfLife').value = product.default_shelf_life_days || 0;
    document.getElementById('productTrackExpiry').checked = product.track_expiry == 1;
    document.getElementById('productIsCarcass').checked = product.is_carcass == 1;
    document.getElementById('productModalTitle').innerHTML =
        '<i class="bi bi-pencil"></i> <?= e(t('edit_product')) ?>';

    var modal = new bootstrap.Modal(document.getElementById('productModal'));
    modal.show();
}

function confirmDeleteProduct(id, name) {
    document.getElementById('deleteProductId').value = id;
    document.getElementById('deleteProductName').textContent = name;
    var modal = new bootstrap.Modal(document.getElementById('deleteProductModal'));
    modal.show();
}
</script>