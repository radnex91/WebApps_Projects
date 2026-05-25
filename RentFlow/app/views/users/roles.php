<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-shield-lock"></i> Matrice des Permissions</h2>
    <a href="<?= BASE_URL ?>/users" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour aux utilisateurs</a>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="bi bi-grid-3x3"></i> Matrice des Permissions par Rôle</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 200px;">Module / Permission</th>
                        <?php foreach ($roles as $role): ?>
                            <th class="text-center" style="min-width: 120px;">
                                <?= htmlspecialchars($role['label']) ?>
                                <br><small class="text-muted"><?= $role['users_count'] ?> user(s)</small>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $currentModule = '';
                    foreach ($permissions as $permission):
                        if ($permission['module'] !== $currentModule):
                            if ($currentModule !== '') echo '</tbody><tbody>';
                            $currentModule = $permission['module'];
                            $moduleIcons = [
                                'dashboard' => 'bi-speedometer2',
                                'landlords' => 'bi-people',
                                'agencies' => 'bi-building',
                                'batches' => 'bi-grid',
                                'payments' => 'bi-cash-stack',
                                'settings' => 'bi-gear',
                                'users' => 'bi-people-fill'
                            ];
                            $icon = $moduleIcons[$currentModule] ?? 'bi-folder';
                    ?>
                        <tr class="table-info">
                            <td colspan="<?= count($roles) + 1 ?>">
                                <strong><i class="bi <?= $icon ?>"></i> <?= ucfirst($currentModule) ?></strong>
                            </td>
                        </tr>
                    <?php endif; ?>
                        <tr>
                            <td class="ps-4">
                                <i class="bi bi-key text-muted"></i> <?= htmlspecialchars($permission['label']) ?>
                            </td>
                            <?php foreach ($roles as $role): ?>
                                <td class="text-center">
                                    <?php $checked = isset($mappings[$role['id']][$permission['id']]); ?>
                                    <div class="form-check d-flex justify-content-center">
                                        <input class="form-check-input matrix-checkbox"
                                               type="checkbox"
                                               data-role-id="<?= $role['id'] ?>"
                                               data-permission-id="<?= $permission['id'] ?>"
                                               <?= $checked ? 'checked' : '' ?>
                                               style="cursor: pointer;">
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <button type="button" class="btn btn-success" id="save-matrix">
                <i class="bi bi-check-lg"></i> Enregistrer la matrice
            </button>
            <span class="ms-2 text-muted"><small>Cliquez sur les cases pour activer/désactiver les permissions par rôle</small></span>
        </div>
    </div>
</div>

<script>
// Save matrix changes
document.getElementById('save-matrix').addEventListener('click', function() {
    const matrixCheckboxes = document.querySelectorAll('.matrix-checkbox');
    const changes = {};

    matrixCheckboxes.forEach(cb => {
        const roleId = cb.dataset.roleId;
        if (!changes[roleId]) {
            changes[roleId] = [];
        }
        if (cb.checked) {
            changes[roleId].push(cb.dataset.permissionId);
        }
    });

    // Save each role's permissions sequentially
    const roleIds = Object.keys(changes);
    let index = 0;

    function saveNext() {
        if (index >= roleIds.length) {
            alert('Matrice enregistrée avec succès!');
            location.reload();
            return;
        }

        const roleId = roleIds[index];
        const params = new URLSearchParams();
        params.append('csrf_token', '<?= Csrf::generateToken() ?>');
        params.append('role_id', roleId);
        changes[roleId].forEach(permId => {
            params.append('permissions[]', permId);
        });

        fetch('<?= BASE_URL ?>/users/roles/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: params.toString()
        })
        .then(response => {
            index++;
            saveNext();
        })
        .catch(err => {
            console.error('Error saving matrix:', err);
            alert('Erreur lors de l\'enregistrement');
        });
    }

    saveNext();
});
</script>
