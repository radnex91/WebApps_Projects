<?php
// ButcheryPOS - Settings Page
use App\Core\Auth;
use App\Core\Permission;

$canManageSensitive = Permission::canManageSensitiveSettings(Auth::get('role_name'));

// Group settings into sections
$sections = [
    'company' => [
        'label' => t('company_info'),
        'icon' => 'bi-building',
        'keys' => ['company_name', 'company_tagline', 'company_phone', 'company_email', 'company_address', 'company_tax_id'],
    ],
    'branding' => [
        'label' => t('branding'),
        'icon' => 'bi-palette',
        'keys' => ['logo_url', 'receipt_footer', 'receipt_header'],
    ],
    'scale' => [
        'label' => t('scale'),
        'icon' => 'bi-speedometer2',
        'keys' => ['scale_port', 'scale_baud_rate', 'scale_enabled'],
    ],
    'expiry' => [
        'label' => t('expiry'),
        'icon' => 'bi-clock-history',
        'keys' => ['expiry_warning_days', 'expiry_critical_days', 'expiry_auto_alert'],
    ],
    'mobile_money' => [
        'label' => t('mobile_money'),
        'icon' => 'bi-phone',
        'keys' => ['mm_provider', 'mm_api_key', 'mm_merchant_id', 'mm_enabled'],
    ],
];

// Identify sensitive keys
$sensitiveKeys = [];
$sensitiveRows = $repo->query("SELECT setting_key FROM app_settings WHERE is_sensitive = 1");
foreach ($sensitiveRows as $row) {
    $sensitiveKeys[] = $row['setting_key'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-gear"></i> <?= t('settings') ?></h4>
</div>

<form method="POST" action="<?= url('?action=update_settings') ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <div class="row">
        <?php foreach ($sections as $sectionKey => $section): ?>
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi <?= $section['icon'] ?>"></i> <?= $section['label'] ?></h6>
                </div>
                <div class="card-body">
                    <?php foreach ($section['keys'] as $key): ?>
                    <?php
                    $value = $appSettings[$key] ?? '';
                    $isSensitive = in_array($key, $sensitiveKeys);
                    $isMasked = $isSensitive && !$canManageSensitive && $value === '********';
                    $isBool = in_array($key, ['scale_enabled', 'expiry_auto_alert', 'mm_enabled']);
                    $isNumber = in_array($key, ['expiry_warning_days', 'expiry_critical_days', 'scale_baud_rate']);
                    $isTextarea = in_array($key, ['receipt_header', 'receipt_footer', 'company_address']);
                    ?>
                    <div class="mb-3">
                        <label class="form-label small"><?= t($key) ?>
                            <?php if ($isSensitive): ?>
                            <i class="bi bi-shield-lock text-warning" title="<?= t('sensitive_setting') ?>"></i>
                            <?php endif; ?>
                        </label>
                        <?php if ($isBool): ?>
                        <div class="form-check form-switch">
                            <input type="hidden" name="settings[<?= e($key) ?>]" value="0">
                            <input type="checkbox" class="form-check-input" name="settings[<?= e($key) ?>]" value="1"
                                   <?= !empty($value) && $value !== '0' && $value !== '********' ? 'checked' : '' ?>
                                   <?= $isMasked ? 'disabled' : '' ?>>
                        </div>
                        <?php elseif ($isTextarea): ?>
                        <textarea name="settings[<?= e($key) ?>]" class="form-control form-control-sm" rows="2"
                                  <?= $isMasked ? 'disabled' : '' ?>><?= e($value) ?></textarea>
                        <?php elseif ($isNumber): ?>
                        <input type="number" name="settings[<?= e($key) ?>]" class="form-control form-control-sm"
                               value="<?= e($value === '********' ? '' : $value) ?>"
                               <?= $isMasked ? 'disabled' : '' ?>>
                        <?php elseif ($key === 'scale_port' || $key === 'mm_provider'): ?>
                        <?php if ($key === 'scale_port'): ?>
                        <select name="settings[<?= e($key) ?>]" class="form-select form-select-sm">
                            <?php foreach (['COM1', 'COM2', 'COM3', 'COM4', '/dev/ttyUSB0', '/dev/ttyS0'] as $port): ?>
                            <option value="<?= e($port) ?>" <?= $value === $port ? 'selected' : '' ?>><?= e($port) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <select name="settings[<?= e($key) ?>]" class="form-select form-select-sm">
                            <option value="orange_money" <?= $value === 'orange_money' ? 'selected' : '' ?>>Orange Money</option>
                            <option value="mtn_momo" <?= $value === 'mtn_momo' ? 'selected' : '' ?>>MTN MoMo</option>
                            <option value="wave" <?= $value === 'wave' ? 'selected' : '' ?>>Wave</option>
                        </select>
                        <?php endif; ?>
                        <?php else: ?>
                        <input type="<?= $isSensitive ? 'password' : 'text' ?>" name="settings[<?= e($key) ?>]"
                               class="form-control form-control-sm"
                               value="<?= e($value === '********' ? '' : $value) ?>"
                               <?= $isMasked ? 'disabled' : '' ?>
                               placeholder="<?= t($key) ?>">
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Sensitive Settings Section -->
    <?php if ($canManageSensitive): ?>
    <div class="card border-warning mb-3">
        <div class="card-header bg-warning bg-opacity-10">
            <h6 class="mb-0 text-warning">
                <i class="bi bi-exclamation-triangle"></i> <?= t('sensitive_settings') ?>
            </h6>
        </div>
        <div class="card-body">
            <div class="alert alert-warning py-2 mb-3">
                <i class="bi bi-shield-exclamation"></i>
                <?= t('sensitive_settings_warning') ?>
            </div>
            <?php
            $standaloneSensitive = [];
            foreach ($sensitiveKeys as $sk) {
                $found = false;
                foreach ($sections as $sec) {
                    if (in_array($sk, $sec['keys'])) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $standaloneSensitive[] = $sk;
                }
            }
            ?>
            <?php foreach ($standaloneSensitive as $sk): ?>
            <div class="mb-3">
                <label class="form-label small"><?= t($sk) ?> <i class="bi bi-shield-lock text-warning"></i></label>
                <input type="password" name="settings[<?= e($sk) ?>]" class="form-control form-control-sm"
                       value="" placeholder="<?= t($sk) ?>">
                <div class="form-text"><?= t('leave_blank_to_keep') ?></div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($standaloneSensitive)): ?>
            <p class="text-muted small mb-0"><?= t('all_sensitive_settings_in_sections') ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-end mb-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> <?= t('save_settings') ?>
        </button>
    </div>
</form>