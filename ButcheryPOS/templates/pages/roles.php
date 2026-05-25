<?php
// ButcheryPOS - Roles & Permissions Page
use App\Core\Auth;
use App\Core\Permission;

$roles = $rbacService->getAllRoles();
$modules = $rbacService->getModuleKeys();
$selectedRoleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : ($roles[0]['id'] ?? 0);
$selectedRole = $selectedRoleId ? $rbacService->getRoleWithPermissions($selectedRoleId) : null;

// Build permission map for selected role
$permMap = [];
if ($selectedRole && !empty($selectedRole['permissions'])) {
    foreach ($selectedRole['permissions'] as $p) {
        $permMap[$p['module_key']] = [
            'can_view'   => (bool)$p['can_view'],
            'can_create' => (bool)$p['can_create'],
            'can_edit'   => (bool)$p['can_edit'],
            'can_delete' => (bool)$p['can_delete'],
            'can_print'  => (bool)$p['can_print'],
            'can_manage' => (bool)$p['can_manage'],
        ];
    }
}

$permColumns = ['can_view', 'can_create', 'can_edit', 'can_delete', 'can_print', 'can_manage'];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-shield-lock"></i> <?= t('roles_permissions') ?></h4>
</div>

<!-- Add Role Form -->
<?php if (Permission::currentUserCan('roles', 'can_create')): ?>
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="POST" action="<?= url('?action=create_role') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <div class="col-md-3">
                <label class="form-label small mb-1"><?= t('role_name') ?> <span class="text-danger">*</span></label>
                <input type="text" name="role_name" class="form-control form-control-sm" required
                       placeholder="<?= t('role_name') ?>" pattern="[a-z_]+"
                       title="<?= t('role_name_format_hint') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1"><?= t('display_name') ?> <span class="text-danger">*</span></label>
                <input type="text" name="display_name" class="form-control form-control-sm" required
                       placeholder="<?= t('display_name') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1"><?= t('description') ?></label>
                <input type="text" name="description" class="form-control form-control-sm"
                       placeholder="<?= t('description') ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-plus-lg"></i> <?= t('add_role') ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <!-- Roles List -->
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0"><?= t('roles') ?></h6>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($roles)): ?>
                <div class="list-group-item text-center text-muted py-4"><?= t('no_roles_found') ?></div>
                <?php else: ?>
                <?php foreach ($roles as $r): ?>
                <a href="<?= url('?page=roles&role_id=' . (int)$r['id']) ?>"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= (int)$r['id'] === $selectedRoleId ? 'active' : '' ?>">
                    <div>
                        <strong><?= e($r['display_name'] ?? $r['role_name']) ?></strong>
                        <?php if (!empty($r['description'])): ?>
                        <br><small class="<?= (int)$r['id'] === $selectedRoleId ? '' : 'text-muted' ?>"><?= e($r['description']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <?php if (!empty($r['is_system_role'])): ?>
                        <span class="badge bg-dark"><?= t('system') ?></span>
                        <?php elseif (Permission::currentUserCan('roles', 'can_delete')): ?>
                        <form method="POST" action="<?= url('?action=delete_role') ?>" class="d-inline"
                              onsubmit="return confirm('<?= t('confirm_delete_role') ?>')">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger border-0 py-0" title="<?= t('delete') ?>"
                                    onclick="event.stopPropagation()">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Permission Matrix -->
    <div class="col-md-8 mb-3">
        <?php if ($selectedRole): ?>
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <?= t('permissions_for') ?>: <?= e($selectedRole['display_name'] ?? $selectedRole['role_name']) ?>
                    <?php if (!empty($selectedRole['is_system_role'])): ?>
                    <span class="badge bg-dark ms-2"><?= t('system_role') ?></span>
                    <?php endif; ?>
                </h6>
            </div>
            <?php if (Permission::currentUserCan('roles', 'can_edit')): ?>
            <form method="POST" action="<?= url('?action=update_permissions') ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="role_id" value="<?= (int)$selectedRoleId ?>">
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?= t('module') ?></th>
                                <?php foreach ($permColumns as $col): ?>
                                <th class="text-center">
                                    <small><?= t($col) ?></small>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <th></th>
                                <?php foreach ($permColumns as $col): ?>
                                <th class="text-center">
                                    <input type="checkbox" class="form-check-input perm-toggle-all"
                                           data-column="<?= e($col) ?>"
                                           title="<?= t('toggle_all') ?> <?= t($col) ?>">
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($modules as $mod): ?>
                            <tr>
                                <td>
                                    <strong><?= t($mod) ?></strong>
                                </td>
                                <?php foreach ($permColumns as $col): ?>
                                <td class="text-center">
                                    <?php
                                    $checked = !empty($permMap[$mod][$col]);
                                    // Admin system role: all perms forced on
                                    if (!empty($selectedRole['is_system_role']) && $selectedRole['role_name'] === 'admin') {
                                        $checked = true;
                                    }
                                    ?>
                                    <?php if (Permission::currentUserCan('roles', 'can_edit') && empty($selectedRole['is_system_role'])): ?>
                                    <input type="checkbox" class="form-check-input perm-check"
                                           name="permissions[<?= e($mod) ?>][<?= e($col) ?>]"
                                           value="1" data-column="<?= e($col) ?>"
                                           <?= $checked ? 'checked' : '' ?>>
                                    <?php else: ?>
                                    <input type="checkbox" class="form-check-input" disabled
                                           <?= $checked ? 'checked' : '' ?>>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (Permission::currentUserCan('roles', 'can_edit') && empty($selectedRole['is_system_role'])): ?>
                <div class="card-footer d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> <?= t('save_permissions') ?>
                    </button>
                </div>
            </form>
            <?php elseif (!empty($selectedRole['is_system_role'])): ?>
            <div class="card-footer">
                <div class="alert alert-info mb-0 py-2 small">
                    <i class="bi bi-info-circle"></i> <?= t('system_role_permissions_readonly') ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="card h-100">
            <div class="card-body d-flex align-items-center justify-content-center text-muted">
                <div class="text-center">
                    <i class="bi bi-shield-lock fs-1"></i>
                    <p class="mt-2"><?= t('select_role_to_edit_permissions') ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Toggle all checkboxes in a column
document.querySelectorAll('.perm-toggle-all').forEach(function(toggle) {
    toggle.addEventListener('change', function() {
        var col = this.getAttribute('data-column');
        var checked = this.checked;
        document.querySelectorAll('.perm-check[data-column="' + col + '"]').forEach(function(cb) {
            cb.checked = checked;
        });
    });
});
</script>