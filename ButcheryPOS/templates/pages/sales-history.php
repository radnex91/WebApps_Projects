<?php
// ButcheryPOS - Sales History Page
use App\Core\Auth;
use App\Core\Permission;

$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$sales = $saleService->getSalesHistory($dateFrom, $dateTo);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-receipt"></i> <?= t('sales_history') ?></h4>
</div>

<!-- Date Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="<?= url('') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="sales">
            <div class="col-md-3 col-sm-6">
                <label class="form-label small mb-1"><?= t('date_from') ?></label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label small mb-1"><?= t('date_to') ?></label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-md-2 col-sm-6">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-funnel"></i> <?= t('filter') ?>
                </button>
            </div>
            <div class="col-md-2 col-sm-6">
                <a href="<?= url('?page=sales') ?>" class="btn btn-sm btn-outline-secondary w-100">
                    <?= t('clear') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Sales Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th><?= t('reference') ?></th>
                    <th><?= t('date') ?></th>
                    <th><?= t('customer') ?></th>
                    <th><?= t('total') ?></th>
                    <th><?= t('payment_method') ?></th>
                    <th><?= t('status') ?></th>
                    <th><?= t('cashier') ?></th>
                    <th class="text-end"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sales)): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4"><?= t('no_sales_found') ?></td>
                </tr>
                <?php else: ?>
                <?php $idx = 0; foreach ($sales as $sale): $idx++; ?>
                <tr>
                    <td><?= $idx ?></td>
                    <td><strong><?= e($sale['reference']) ?></strong></td>
                    <td><?= format_date($sale['created_at']) ?></td>
                    <td><?= e($sale['customer_name'] ?? '-') ?></td>
                    <td><strong><?= money((float)$sale['total_amount']) ?></strong></td>
                    <td>
                        <?php
                        $methodIcons = ['cash' => 'bi-cash-stack', 'mobile_money' => 'bi-phone', 'card' => 'bi-credit-card'];
                        $method = $sale['payment_method'] ?? 'cash';
                        $icon = $methodIcons[$method] ?? 'bi-cash-stack';
                        ?>
                        <i class="bi <?= $icon ?>"></i> <?= t($method) ?>
                    </td>
                    <td>
                        <?php
                        $status = $sale['payment_status'] ?? 'paid';
                        $statusClasses = ['paid' => 'bg-success', 'partial' => 'bg-warning text-dark', 'unpaid' => 'bg-danger'];
                        $cls = $statusClasses[$status] ?? 'bg-secondary';
                        ?>
                        <span class="badge <?= $cls ?>"><?= t($status) ?></span>
                    </td>
                    <td><?= e($sale['created_by_name'] ?? '-') ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewReceipt(<?= (int)$sale['id'] ?>)"
                                title="<?= t('view_receipt') ?>">
                            <i class="bi bi-eye"></i>
                        </button>
                        <?php if (Permission::currentUserCan('sales', 'can_print')): ?>
                        <button class="btn btn-sm btn-outline-secondary" onclick="printReceipt(<?= (int)$sale['id'] ?>)"
                                title="<?= t('print_receipt') ?>">
                            <i class="bi bi-printer"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Receipt Detail Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="receiptModalLabel"><?= t('receipt_details') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button>
            </div>
            <div class="modal-body" id="receiptContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <?php if (Permission::currentUserCan('sales', 'can_print')): ?>
                <button type="button" class="btn btn-outline-secondary" onclick="printReceiptContent()">
                    <i class="bi bi-printer"></i> <?= t('print_receipt') ?>
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('close') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
function viewReceipt(saleId) {
    var modal = new bootstrap.Modal(document.getElementById('receiptModal'));
    document.getElementById('receiptContent').innerHTML =
        '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    modal.show();

    fetch('<?= url('') ?>?page=sales&action=receipt&id=' + saleId)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (!data.sale) {
                document.getElementById('receiptContent').innerHTML =
                    '<div class="alert alert-danger"><?= t('sale_not_found') ?></div>';
                return;
            }
            var s = data.sale;
            var html = '<div id="printableReceipt">' +
                '<div class="text-center mb-3">' +
                    '<h5>' + <?= json_encode(e($appSettings['company_name'] ?? 'ButcheryPOS')) ?> + '</h5>' +
                    '<p class="mb-1 small">' + <?= json_encode(e($appSettings['company_address'] ?? '')) ?> + '</p>' +
                    '<p class="mb-1 small">' + <?= json_encode(e($appSettings['company_phone'] ?? '')) ?> + '</p>' +
                '</div>' +
                '<hr>' +
                '<div class="row small mb-2">' +
                    '<div class="col-6"><strong><?= t('reference') ?>:</strong> ' + (s.reference || '') + '</div>' +
                    '<div class="col-6 text-end"><strong><?= t('date') ?>:</strong> ' + (s.created_at || '') + '</div>' +
                '</div>' +
                '<div class="row small mb-2">' +
                    '<div class="col-6"><strong><?= t('customer') ?>:</strong> ' + (s.customer_name || '-') + '</div>' +
                    '<div class="col-6 text-end"><strong><?= t('cashier') ?>:</strong> ' + (s.created_by_name || '-') + '</div>' +
                '</div>' +
                '<hr>' +
                '<table class="table table-sm mb-0">' +
                    '<thead><tr><th><?= t('product') ?></th><th class="text-center"><?= t('qty') ?></th><th class="text-end"><?= t('price') ?></th><th class="text-end"><?= t('subtotal') ?></th></tr></thead>' +
                    '<tbody>';

            if (s.items) {
                for (var i = 0; i < s.items.length; i++) {
                    var item = s.items[i];
                    html += '<tr>' +
                        '<td>' + (item.product_name || '') + '</td>' +
                        '<td class="text-center">' + (item.quantity || 0) + '</td>' +
                        '<td class="text-end">' + (item.unit_price || 0) + '</td>' +
                        '<td class="text-end">' + (item.subtotal || 0) + '</td>' +
                    '</tr>';
                }
            }

            html += '</tbody></table>' +
                '<hr>' +
                '<div class="row">' +
                    '<div class="col-6"><strong><?= t('subtotal') ?>:</strong></div>' +
                    '<div class="col-6 text-end">' + (s.subtotal || 0) + '</div>' +
                '</div>';

            if (parseFloat(s.discount_amount || 0) > 0) {
                html += '<div class="row text-danger">' +
                    '<div class="col-6"><strong><?= t('discount') ?>:</strong></div>' +
                    '<div class="col-6 text-end">-' + (s.discount_amount || 0) + '</div>' +
                '</div>';
            }

            html += '<div class="row fw-bold fs-5">' +
                    '<div class="col-6"><?= t('total') ?>:</div>' +
                    '<div class="col-6 text-end">' + (s.total_amount || 0) + '</div>' +
                '</div>';

            if (s.payments && s.payments.length > 0) {
                html += '<hr><p class="mb-1 fw-bold"><?= t('payments') ?></p>';
                for (var j = 0; j < s.payments.length; j++) {
                    var p = s.payments[j];
                    html += '<div class="row small">' +
                        '<div class="col-6">' + (p.method || '') + '</div>' +
                        '<div class="col-6 text-end">' + (p.amount || 0) + '</div>' +
                    '</div>';
                }
            }

            if (s.notes) {
                html += '<hr><p class="small text-muted"><strong><?= t('notes') ?>:</strong> ' + s.notes + '</p>';
            }

            html += '</div>';
            document.getElementById('receiptContent').innerHTML = html;
        })
        .catch(function() {
            document.getElementById('receiptContent').innerHTML =
                '<div class="alert alert-danger"><?= t('error_loading_receipt') ?></div>';
        });
}

function printReceipt(saleId) {
    viewReceipt(saleId);
    setTimeout(function() { printReceiptContent(); }, 1500);
}

function printReceiptContent() {
    var content = document.getElementById('printableReceipt');
    if (!content) return;
    var win = window.open('', '_blank', 'width=400,height=600');
    win.document.write('<html><head><title><?= t('receipt') ?></title>' +
        '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">' +
        '<style>body{padding:10px;font-size:12px;} .table td,.table th{padding:4px 6px;font-size:12px;}</style>' +
        '</head><body>' + content.innerHTML + '</body></html>');
    win.document.close();
    win.focus();
    win.print();
    win.close();
}
</script>