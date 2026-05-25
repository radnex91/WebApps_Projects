<?php
// ButcheryPOS - Expiry Dashboard Page
use App\Core\Auth;
use App\Core\Permission;

$alerts = $expiryService->getActiveAlerts();

// Count by type
$expiredCount = 0;
$criticalCount = 0;
$warningCount = 0;
foreach ($alerts as $a) {
    switch ($a['alert_type']) {
        case 'expired': $expiredCount++; break;
        case 'critical': $criticalCount++; break;
        case 'warning': $warningCount++; break;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-clock-history"></i> <?= t('expiry_dashboard') ?></h4>
    <div class="d-flex gap-2">
        <?php if (Permission::currentUserCan('expiry', 'can_manage')): ?>
        <form method="POST" action="<?= url('?action=generate_expiry_alerts') ?>" class="d-inline">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit" class="btn btn-outline-primary">
                <i class="bi bi-lightning"></i> <?= t('generate_alerts') ?>
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card border-danger">
            <div class="card-body text-center">
                <div class="fs-2 text-danger fw-bold"><?= $expiredCount ?></div>
                <div class="small text-muted"><?= t('expired') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-warning">
            <div class="card-body text-center">
                <div class="fs-2 text-warning fw-bold"><?= $criticalCount ?></div>
                <div class="small text-muted"><?= t('critical') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-info">
            <div class="card-body text-center">
                <div class="fs-2 text-info fw-bold"><?= $warningCount ?></div>
                <div class="small text-muted"><?= t('warning') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Alerts Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><?= t('expiry_alerts') ?></h6>
        <span class="badge bg-secondary"><?= count($alerts) ?> <?= t('alerts') ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= t('status') ?></th>
                    <th><?= t('product') ?></th>
                    <th><?= t('batch') ?></th>
                    <th><?= t('expiry_date') ?></th>
                    <th><?= t('days_remaining') ?></th>
                    <th><?= t('quantity_remaining') ?></th>
                    <th class="text-end"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($alerts)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="bi bi-check-circle fs-3 text-success"></i>
                        <p class="mt-2 mb-0"><?= t('no_expiry_alerts') ?></p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($alerts as $a): ?>
                <?php
                $rowClass = '';
                $badgeClass = '';
                $badgeText = '';
                switch ($a['alert_type']) {
                    case 'expired':
                        $rowClass = 'table-danger';
                        $badgeClass = 'bg-danger';
                        $badgeText = t('expired');
                        break;
                    case 'critical':
                        $rowClass = 'table-warning';
                        $badgeClass = 'bg-warning text-dark';
                        $badgeText = t('critical');
                        break;
                    case 'warning':
                        $rowClass = '';
                        $badgeClass = 'bg-info text-dark';
                        $badgeText = t('warning');
                        break;
                }
                $days = (int)($a['days_until_expiry'] ?? 0);
                ?>
                <tr class="<?= $rowClass ?>">
                    <td>
                        <span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                    </td>
                    <td>
                        <strong><?= e($a['product_name'] ?? '') ?></strong>
                    </td>
                    <td>
                        <code><?= e($a['batch_reference'] ?? '') ?></code>
                    </td>
                    <td>
                        <?= format_date_short($a['expiry_date'] ?? null) ?>
                    </td>
                    <td>
                        <?php if ($days <= 0): ?>
                        <span class="text-danger fw-bold">
                            <i class="bi bi-exclamation-circle"></i>
                            <?= $days === 0 ? t('today') : abs($days) . ' ' . t('days_ago') ?>
                        </span>
                        <?php else: ?>
                        <span class="<?= $days <= 1 ? 'text-danger fw-bold' : 'text-warning fw-bold' ?>">
                            <?= $days ?> <?= t('days') ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= number_format((float)($a['quantity_remaining'] ?? 0), 2) ?>
                        <?= e($a['unit_abbr'] ?? '') ?>
                    </td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-1">
                            <?php if (Permission::currentUserCan('expiry', 'can_edit')): ?>
                            <form method="POST" action="<?= url('?action=dismiss_expiry_alert') ?>" class="d-inline"
                                  onsubmit="return confirm('<?= t('confirm_dismiss_alert') ?>')">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="alert_id" value="<?= (int)$a['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary" title="<?= t('dismiss') ?>">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if (Permission::currentUserCan('expiry', 'can_manage')): ?>
                            <form method="POST" action="<?= url('?action=mark_batch_waste') ?>" class="d-inline"
                                  onsubmit="return confirm('<?= t('confirm_mark_waste') ?>')">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="batch_id" value="<?= (int)$a['batch_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="<?= t('mark_as_waste') ?>">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>