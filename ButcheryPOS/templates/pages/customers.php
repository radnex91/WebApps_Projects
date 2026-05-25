<?php
// ButcheryPOS - Customers Page
use App\Core\Auth;
use App\Core\Permission;

$customers = $customerModel->all([], 'name ASC');
$searchQuery = $_GET['q'] ?? '';

if ($searchQuery !== '') {
    $customers = array_filter($customers, function ($c) use ($searchQuery) {
        return stripos($c['name'], $searchQuery) !== false
            || stripos($c['phone'] ?? '', $searchQuery) !== false;
    });
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-people"></i> <?= t('customers') ?></h4>
    <?php if (Permission::currentUserCan('customers', 'can_create')): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="openCustomerForm()">
        <i class="bi bi-plus-lg"></i> <?= t('add_customer') ?>
    </button>
    <?php endif; ?>
</div>

<!-- Search -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="<?= url('') ?>" class="row g-2 align-items-center">
            <input type="hidden" name="page" value="customers">
            <div class="col-auto">
                <i class="bi bi-search text-muted"></i>
            </div>
            <div class="col">
                <input type="text" name="q" class="form-control form-control-sm" value="<?= e($searchQuery) ?>"
                       placeholder="<?= t('search_by_name_or_phone') ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary"><?= t('search') ?></button>
                <?php if ($searchQuery !== ''): ?>
                <a href="<?= url('?page=customers') ?>" class="btn btn-sm btn-outline-secondary ms-1"><?= t('clear') ?></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Customers Table -->
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
                <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4"><?= t('no_customers_found') ?></td>
                </tr>
                <?php else: ?>
                <?php $idx = 0; foreach ($customers as $c): $idx++; ?>
                <tr class="<?= empty($c['is_active']) ? 'opacity-50' : '' ?>">
                    <td><?= $idx ?></td>
                    <td>
                        <strong><?= e($c['name']) ?></strong>
                        <?php if (!empty($c['address'])): ?>
                        <br><small class="text-muted"><?= e($c['address']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= e($c['phone'] ?? '-') ?></td>
                    <td><?= e($c['email'] ?? '-') ?></td>
                    <td>
                        <?php $bal = (float)($c['balance'] ?? 0); ?>
                        <span class="<?= $bal < 0 ? 'text-danger' : ($bal > 0 ? 'text-success' : '') ?>">
                            <?= money($bal) ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($c['is_active'])): ?>
                        <span class="badge bg-success"><?= t('active') ?></span>
                        <?php else: ?>
                        <span class="badge bg-secondary"><?= t('inactive') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if (Permission::currentUserCan('customers', 'can_edit')): ?>
                        <button class="btn btn-sm btn-outline-primary" onclick='openCustomerForm(<?= json_encode($c) ?>)'
                                title="<?= t('edit') ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php endif; ?>
                        <?php if (Permission::currentUserCan('customers', 'can_delete')): ?>
                        <form method="POST" action="<?= url('?action=delete_customer') ?>" class="d-inline"
                              onsubmit="return confirm('<?= t('confirm_delete_customer') ?>')">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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

<!-- Add/Edit Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= url('?action=save_customer') ?>" id="customerForm">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" id="customerId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="customerModalLabel"><?= t('add_customer') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="customerName" class="form-label"><?= t('name') ?> <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="customerName" class="form-control" required
                               placeholder="<?= t('customer_name') ?>">
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="customerPhone" class="form-label"><?= t('phone') ?></label>
                            <input type="text" name="phone" id="customerPhone" class="form-control"
                                   placeholder="<?= t('phone') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="customerEmail" class="form-label"><?= t('email') ?></label>
                            <input type="email" name="email" id="customerEmail" class="form-control"
                                   placeholder="<?= t('email') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="customerAddress" class="form-label"><?= t('address') ?></label>
                        <textarea name="address" id="customerAddress" class="form-control" rows="2"
                                  placeholder="<?= t('address') ?>"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="customerBalance" class="form-label"><?= t('balance') ?></label>
                        <input type="number" name="balance" id="customerBalance" class="form-control" step="0.01"
                               value="0">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="customerActive" class="form-check-input" value="1" checked>
                        <label for="customerActive" class="form-check-label"><?= t('active') ?></label>
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
function openCustomerForm(customer) {
    var form = document.getElementById('customerForm');
    form.reset();
    document.getElementById('customerId').value = '';

    if (customer) {
        document.getElementById('customerModalLabel').textContent = '<?= t('edit_customer') ?>';
        document.getElementById('customerId').value = customer.id;
        document.getElementById('customerName').value = customer.name || '';
        document.getElementById('customerPhone').value = customer.phone || '';
        document.getElementById('customerEmail').value = customer.email || '';
        document.getElementById('customerAddress').value = customer.address || '';
        document.getElementById('customerBalance').value = customer.balance || 0;
        document.getElementById('customerActive').checked = customer.is_active == 1;
    } else {
        document.getElementById('customerModalLabel').textContent = '<?= t('add_customer') ?>';
    }
}
</script>