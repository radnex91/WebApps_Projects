<?php
// ButcheryPOS - Suppliers Page
use App\Core\Auth;
use App\Core\Permission;

$suppliers = $supplierModel->all([], 'name ASC');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-truck"></i> <?= t('suppliers') ?></h4>
    <?php if (Permission::currentUserCan('suppliers', 'can_create')): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="openSupplierForm()">
        <i class="bi bi-plus-lg"></i> <?= t('add_supplier') ?>
    </button>
    <?php endif; ?>
</div>

<!-- Suppliers Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th><?= t('name') ?></th>
                    <th><?= t('phone') ?></th>
                    <th><?= t('email') ?></th>
                    <th><?= t('balance') ?></th>
                    <th><?= t('status') ?></th>
                    <th class="text-end"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4"><?= t('no_suppliers_found') ?></td>
                </tr>
                <?php else: ?>
                <?php $idx = 0; foreach ($suppliers as $s): $idx++; ?>
                <tr class="<?= empty($s['is_active']) ? 'opacity-50' : '' ?>">
                    <td><?= $idx ?></td>
                    <td>
                        <strong><?= e($s['name']) ?></strong>
                        <?php if (!empty($s['address'])): ?>
                        <br><small class="text-muted"><?= e($s['address']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= e($s['phone'] ?? '-') ?></td>
                    <td><?= e($s['email'] ?? '-') ?></td>
                    <td>
                        <?php $bal = (float)($s['balance'] ?? 0); ?>
                        <span class="<?= $bal < 0 ? 'text-danger' : ($bal > 0 ? 'text-success' : '') ?>">
                            <?= money($bal) ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($s['is_active'])): ?>
                        <span class="badge bg-success"><?= t('active') ?></span>
                        <?php else: ?>
                        <span class="badge bg-secondary"><?= t('inactive') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if (Permission::currentUserCan('suppliers', 'can_edit')): ?>
                        <button class="btn btn-sm btn-outline-primary" onclick='openSupplierForm(<?= json_encode($s) ?>)'
                                title="<?= t('edit') ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php endif; ?>
                        <?php if (Permission::currentUserCan('suppliers', 'can_delete')): ?>
                        <form method="POST" action="<?= url('?action=delete_supplier') ?>" class="d-inline"
                              onsubmit="return confirm('<?= t('confirm_delete_supplier') ?>')">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" title="<?= t('delete') ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1" aria-labelledby="supplierModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= url('?action=save_supplier') ?>" id="supplierForm">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" id="supplierId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="supplierModalLabel"><?= t('add_supplier') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="supplierName" class="form-label"><?= t('name') ?> <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="supplierName" class="form-control" required
                               placeholder="<?= t('supplier_name') ?>">
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="supplierPhone" class="form-label"><?= t('phone') ?></label>
                            <input type="text" name="phone" id="supplierPhone" class="form-control"
                                   placeholder="<?= t('phone') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="supplierEmail" class="form-label"><?= t('email') ?></label>
                            <input type="email" name="email" id="supplierEmail" class="form-control"
                                   placeholder="<?= t('email') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="supplierAddress" class="form-label"><?= t('address') ?></label>
                        <textarea name="address" id="supplierAddress" class="form-control" rows="2"
                                  placeholder="<?= t('address') ?>"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="supplierBalance" class="form-label"><?= t('balance') ?></label>
                        <input type="number" name="balance" id="supplierBalance" class="form-control" step="0.01"
                               value="0">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="supplierActive" class="form-check-input" value="1" checked>
                        <label for="supplierActive" class="form-check-label"><?= t('active') ?></label>
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

<script>
function openSupplierForm(supplier) {
    var form = document.getElementById('supplierForm');
    form.reset();
    document.getElementById('supplierId').value = '';

    if (supplier) {
        document.getElementById('supplierModalLabel').textContent = '<?= t('edit_supplier') ?>';
        document.getElementById('supplierId').value = supplier.id;
        document.getElementById('supplierName').value = supplier.name || '';
        document.getElementById('supplierPhone').value = supplier.phone || '';
        document.getElementById('supplierEmail').value = supplier.email || '';
        document.getElementById('supplierAddress').value = supplier.address || '';
        document.getElementById('supplierBalance').value = supplier.balance || 0;
        document.getElementById('supplierActive').checked = supplier.is_active == 1;
    } else {
        document.getElementById('supplierModalLabel').textContent = '<?= t('add_supplier') ?>';
    }
}
</script>