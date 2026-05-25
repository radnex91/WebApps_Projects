<?php
// ButcheryPOS - Breakdown (Découpe) Page
use App\Core\Auth;
use App\Core\Permission;

$breakdowns = $breakdownService->getBreakdowns(50);
$carcassProducts = $breakdownService->getCarcassProducts();
$retailProducts = $breakdownService->getRetailProducts();

// View breakdown detail via GET param
$viewBreakdown = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $viewBreakdown = $breakdownService->getBreakdownDetail((int)$_GET['view']);
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><?= t('breakdown') ?></h4>
        <small class="text-muted"><?= count($breakdowns) ?> <?= t('breakdowns_found') ?></small>
    </div>
    <?php if (Permission::currentUserCan('breakdown', 'can_create')): ?>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#breakdownModal" onclick="BreakdownJS.resetBreakdownForm()">
        <i class="bi bi-scissors"></i> <?= t('record_breakdown') ?>
    </button>
    <?php endif; ?>
</div>

<!-- Breakdown Detail View -->
<?php if ($viewBreakdown): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="bi bi-scissors"></i> <?= t('breakdown_detail') ?> — <?= e($viewBreakdown['reference']) ?>
        </h6>
        <a href="<?= url('?page=breakdown') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> <?= t('back') ?>
        </a>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-3">
                <strong><?= t('source_product') ?>:</strong><br>
                <?= e($viewBreakdown['source_product_name'] ?? '-') ?>
            </div>
            <div class="col-md-3">
                <strong><?= t('source_batch') ?>:</strong><br>
                <?= e($viewBreakdown['source_batch_ref'] ?? '-') ?>
            </div>
            <div class="col-md-2">
                <strong><?= t('source_quantity') ?>:</strong><br>
                <?= (float)$viewBreakdown['source_quantity'] ?> kg
            </div>
            <div class="col-md-2">
                <strong><?= t('waste_weight') ?>:</strong><br>
                <?= (float)$viewBreakdown['waste_weight'] ?> kg
            </div>
            <div class="col-md-2">
                <strong><?= t('date') ?>:</strong><br>
                <?= format_date($viewBreakdown['created_at'], 'd/m/Y H:i') ?>
            </div>
        </div>
        <?php if (!empty($viewBreakdown['notes'])): ?>
        <div class="mb-3">
            <strong><?= t('notes') ?>:</strong> <?= e($viewBreakdown['notes']) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($viewBreakdown['items'])): ?>
        <h6><?= t('output_items') ?></h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead class="table-light">
                    <tr>
                        <th><?= t('product') ?></th>
                        <th class="text-end"><?= t('quantity') ?> (kg)</th>
                        <th class="text-end"><?= t('unit_cost') ?></th>
                        <th><?= t('type') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($viewBreakdown['items'] as $item): ?>
                    <tr>
                        <td><?= e($item['output_product_name'] ?? '-') ?></td>
                        <td class="text-end"><?= (float)$item['output_quantity'] ?></td>
                        <td class="text-end"><?= money((float)$item['output_unit_cost']) ?></td>
                        <td>
                            <?php if ($item['is_byproduct']): ?>
                                <span class="badge bg-secondary"><?= t('byproduct') ?></span>
                            <?php else: ?>
                                <span class="badge bg-primary"><?= t('main_product') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td><?= t('total') ?></td>
                        <td class="text-end"><?= (float)$viewBreakdown['total_output_weight'] ?> kg</td>
                        <td></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Breakdown History Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($breakdowns)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-scissors fs-1"></i>
            <p class="mt-2"><?= t('no_breakdowns_found') ?></p>
            <?php if (Permission::currentUserCan('breakdown', 'can_create')): ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#breakdownModal" onclick="BreakdownJS.resetBreakdownForm()">
                <i class="bi bi-scissors"></i> <?= t('record_breakdown') ?>
            </button>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= t('reference') ?></th>
                        <th><?= t('date') ?></th>
                        <th><?= t('source_product') ?></th>
                        <th class="text-end"><?= t('source_quantity') ?></th>
                        <th class="text-end"><?= t('total_output') ?></th>
                        <th class="text-end"><?= t('waste_weight') ?></th>
                        <th><?= t('by') ?></th>
                        <th class="text-center"><?= t('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($breakdowns as $bd): ?>
                    <tr>
                        <td><strong><?= e($bd['reference']) ?></strong></td>
                        <td><?= format_date($bd['created_at'], 'd/m/Y H:i') ?></td>
                        <td><?= e($bd['source_product_name'] ?? '-') ?></td>
                        <td class="text-end"><?= (float)$bd['source_quantity'] ?> kg</td>
                        <td class="text-end"><?= (float)$bd['total_output_weight'] ?> kg</td>
                        <td class="text-end text-danger"><?= (float)$bd['waste_weight'] ?> kg</td>
                        <td class="text-muted"><?= e($bd['created_by_name'] ?? '-') ?></td>
                        <td class="text-center">
                            <a href="<?= url('?page=breakdown&view=' . (int)$bd['id']) ?>" class="btn btn-sm btn-outline-primary" title="<?= t('view') ?>">
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

<!-- Record Breakdown Modal -->
<div class="modal fade" id="breakdownModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form id="breakdownForm" method="POST" action="<?= url('?action=record_breakdown') ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-scissors"></i> <?= t('record_breakdown') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <!-- Source Section -->
                    <div class="card border-primary mb-3">
                        <div class="card-header bg-primary text-white">
                            <i class="bi bi-box-arrow-in-down"></i> <?= t('source_section') ?>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label"><?= t('source_product') ?> <span class="text-danger">*</span></label>
                                    <select name="source_product_id" id="sourceProductId" class="form-select" required>
                                        <option value=""><?= t('select_carcass_product') ?></option>
                                        <?php foreach ($carcassProducts as $cp): ?>
                                        <option value="<?= (int)$cp['id'] ?>" data-name="<?= e($cp['name']) ?>">
                                            <?= e($cp['name']) ?> (<?= e($cp['sku']) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"><?= t('source_batch') ?> <span class="text-danger">*</span></label>
                                    <select name="source_batch_id" id="sourceBatchId" class="form-select" required>
                                        <option value=""><?= t('select_product_first') ?></option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"><?= t('source_quantity') ?> (kg) <span class="text-danger">*</span></label>
                                    <input type="number" name="source_quantity" id="sourceQuantity" class="form-control" step="0.01" min="0.01" required>
                                    <small class="text-muted" id="batchAvailableInfo"></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Output Section -->
                    <div class="card border-success mb-3">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-box-arrow-up"></i> <?= t('output_section') ?></span>
                            <button type="button" class="btn btn-sm btn-light" onclick="BreakdownJS.addOutputRow()">
                                <i class="bi bi-plus-lg"></i> <?= t('add_cut') ?>
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0" id="outputItemsTable">
                                    <thead>
                                        <tr>
                                            <th><?= t('product') ?></th>
                                            <th class="text-end" style="width:120px;"><?= t('quantity') ?> (kg)</th>
                                            <th class="text-center" style="width:80px;"><?= t('byproduct') ?></th>
                                            <th style="width:40px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="outputItemsBody">
                                        <!-- Dynamic rows inserted by JS -->
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success mt-2" onclick="BreakdownJS.addOutputRow()">
                                <i class="bi bi-plus-lg"></i> <?= t('add_cut') ?>
                            </button>
                        </div>
                    </div>

                    <!-- Reconciliation -->
                    <div class="card border-secondary mb-3">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <label class="form-label"><?= t('waste_weight') ?> (kg)</label>
                                    <input type="number" name="waste_weight" id="wasteWeight" class="form-control" step="0.01" min="0" value="0">
                                </div>
                                <div class="col-md-8">
                                    <div class="d-flex justify-content-around text-center">
                                        <div>
                                            <strong><?= t('source') ?>:</strong><br>
                                            <span id="reconSource" class="fs-5">0</span> kg
                                        </div>
                                        <div class="align-self-center"><i class="bi bi-arrow-right fs-4"></i></div>
                                        <div>
                                            <strong><?= t('outputs') ?>:</strong><br>
                                            <span id="reconOutput" class="fs-5">0</span> kg
                                        </div>
                                        <div>+</div>
                                        <div>
                                            <strong><?= t('waste') ?>:</strong><br>
                                            <span id="reconWaste" class="fs-5 text-danger">0</span> kg
                                        </div>
                                        <div>=</div>
                                        <div>
                                            <strong><?= t('difference') ?>:</strong><br>
                                            <span id="reconDiff" class="fs-5 fw-bold">0</span> kg
                                        </div>
                                    </div>
                                    <div class="text-center mt-1">
                                        <span id="reconStatus" class="badge bg-secondary"><?= t('pending_reconciliation') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-3">
                        <label class="form-label"><?= t('notes') ?></label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="<?= t('breakdown_notes_placeholder') ?>"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-primary" id="submitBreakdownBtn" disabled>
                        <i class="bi bi-scissors"></i> <?= t('record_breakdown') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Breakdown JS Config -->
<script>
window.BreakdownConfig = {
    apiBaseUrl: '<?= url('public/api/index.php?route=api/') ?>',
    retailProducts: <?= json_encode($retailProducts) ?>,
    i18n: {
        loading: <?= json_encode(t('loading')) ?>,
        selectProduct: <?= json_encode(t('select_product')) ?>,
        selectProductFirst: <?= json_encode(t('select_product_first')) ?>,
        selectBatch: <?= json_encode(t('select_batch')) ?>,
        pendingReconciliation: <?= json_encode(t('pending_reconciliation')) ?>,
        weightOk: <?= json_encode(t('weight_ok')) ?>,
        weightMismatch: <?= json_encode(t('weight_mismatch')) ?>,
        delete: <?= json_encode(t('delete')) ?>,
    }
};
</script>