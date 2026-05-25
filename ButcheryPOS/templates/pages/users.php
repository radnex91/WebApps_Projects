<?php
// ButcheryPOS - Users Page
use App\Core\Auth;
use App\Core\Permission;

$users = $userModel->all([], 'full_name ASC');
$roles = $rbacService->getAllRoles();
$roleOptions = [];
foreach ($roles as $r) {
    $roleOptions[$r['id']] = $r['display_name'] ?? $r['role_name'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-person-badge"></i> <?= t('users') ?></h4>
    <?php if (Permission::currentUserCan('users', 'can_create')): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openUserForm()">
        <i class="bi bi-plus-lg"></i> <?= t('add_user') ?>
    </button>
    <?php endif; ?>
</div>

<!-- Users Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th><?= t('full_name') ?></th>
                    <th><?= t('username') ?></th>
                    <th><?= t('role') ?></th>
                    <th><?= t('language') ?></th>
                    <th><?= t('status') ?></th>
                    <th><?= t('last_login') ?></th>
                    <th class="text-end"><?= t('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4"><?= t('no_users_found') ?></td>
                </tr>
                <?php else: ?>
                <?php $idx = 0; foreach ($users as $u): $idx++; ?>
                <tr class="<?= empty($u['is_active']) ? 'opacity-50' : '' ?>">
                    <td><?= $idx ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-circle bg-primary bg-opacity-10 text-primary">
                                <?= mb_strtoupper(mb_substr($u['full_name'] ?? '', 0, 1)) ?>
                            </div>
                            <div>
                                <strong><?= e($u['full_name']) ?></strong>
                                <?php if (!empty($u['email'])): ?>
                                <br><small class="text-muted"><?= e($u['email']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><code><?= e($u['username']) ?></code></td>
                    <td>
                        <?php
                        $userRole = null;
                        foreach ($roles as $r) {
                            if ($r['id'] == $u['role_id']) {
                                $userRole = $r;
                                break;
                            }
                        }
                        ?>
                        <?php if ($userRole): ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary">
                            <?= e($userRole['display_name'] ?? $userRole['role_name']) ?>
                        </span>
                        <?php else: ?>
                        <span class="badge bg-secondary"><?= t('unknown') ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $lng = $u['preferred_language'] ?? 'en'; ?>
                        <span class="badge bg-light text-dark"><?= strtoupper($lng) ?></span>
                    </td>
                    <td>
                        <?php if (!empty($u['is_active'])): ?>
                        <span class="badge bg-success"><?= t('active') ?></span>
                        <?php else: ?>
                        <span class="badge bg-secondary"><?= t('inactive') ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($u['last_login'])): ?>
                        <span class="small"><?= format_date($u['last_login'], 'd/m/Y H:i') ?></span>
                        <?php else: ?>
                        <span class="text-muted small"><?= t('never') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if (Permission::currentUserCan('users', 'can_edit')): ?>
                        <button class="btn btn-sm btn-outline-primary" onclick='openUserForm(<?= json_encode($u) ?>)'
                                title="<?= t('edit') ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php endif; ?>
                        <?php if (Permission::currentUserCan('users', 'can_delete') && (int)$u['id'] !== Auth::get('id')): ?>
                        <form method="POST" action="<?= url('?action=delete_user') ?>" class="d-inline"
                              onsubmit="return confirm('<?= t('confirm_delete_user') ?>')">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
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

<!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= url('?action=create_user') ?>" id="userForm">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" id="userId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel"><?= t('add_user') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="userFullName" class="form-label"><?= t('full_name') ?> <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" id="userFullName" class="form-control" required
                               placeholder="<?= t('full_name') ?>">
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="userUsername" class="form-label"><?= t('username') ?> <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="userUsername" class="form-control" required
                                   placeholder="<?= t('username') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="userEmail" class="form-label"><?= t('email') ?></label>
                            <input type="email" name="email" id="userEmail" class="form-control"
                                   placeholder="<?= t('email') ?>">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="userPassword" class="form-label">
                                <?= t('password') ?>
                                <span class="text-danger" id="passwordRequired">*</span>
                            </label>
                            <input type="password" name="password" id="userPassword" class="form-control"
                                   autocomplete="new-password" placeholder="<?= t('password') ?>">
                            <div class="form-text" id="passwordHint"><?= t('password_required_on_create') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label for="userPhone" class="form-label"><?= t('phone') ?></label>
                            <input type="text" name="phone" id="userPhone" class="form-control"
                                   placeholder="<?= t('phone') ?>">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="userRole" class="form-label"><?= t('role') ?> <span class="text-danger">*</span></label>
                            <select name="role_id" id="userRole" class="form-select" required>
                                <option value=""><?= t('select_role') ?></option>
                                <?php foreach ($roleOptions as $rid => $rname): ?>
                                <option value="<?= (int)$rid ?>"><?= e($rname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="userLanguage" class="form-label"><?= t('language') ?></label>
                            <select name="preferred_language" id="userLanguage" class="form-select">
                                <option value="fr">Fran&ccedil;ais</option>
                                <option value="en">English</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="userActive" class="form-check-input" value="1" checked>
                        <label for="userActive" class="form-check-label"><?= t('active') ?></label>
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
var userRoles = <?= json_encode($roleOptions) ?>;

function openUserForm(user) {
    var form = document.getElementById('userForm');
    form.reset();
    document.getElementById('userId').value = '';

    if (user) {
        document.getElementById('userModalLabel').textContent = '<?= t('edit_user') ?>';
        document.getElementById('userId').value = user.id;
        document.getElementById('userFullName').value = user.full_name || '';
        document.getElementById('userUsername').value = user.username || '';
        document.getElementById('userEmail').value = user.email || '';
        document.getElementById('userPhone').value = user.phone || '';
        document.getElementById('userRole').value = user.role_id || '';
        document.getElementById('userLanguage').value = user.preferred_language || 'fr';
        document.getElementById('userActive').checked = user.is_active == 1;

        // Password optional on edit
        document.getElementById('userPassword').removeAttribute('required');
        document.getElementById('passwordRequired').style.display = 'none';
        document.getElementById('passwordHint').textContent = '<?= t('leave_blank_to_keep_password') ?>';

        // Switch form action to update_user for edit
        form.action = '<?= url('?action=update_user') ?>';
    } else {
        document.getElementById('userModalLabel').textContent = '<?= t('add_user') ?>';

        // Password required on create
        document.getElementById('userPassword').setAttribute('required', 'required');
        document.getElementById('passwordRequired').style.display = '';
        document.getElementById('passwordHint').textContent = '<?= t('password_required_on_create') ?>';

        // Switch form action to create_user for new user
        form.action = '<?= url('?action=create_user') ?>';
    }
}
</script>