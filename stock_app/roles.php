<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

require_permission('manage_roles');

$permissionsMap = available_permissions();
$editRole = null;

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $editStmt = $conn->prepare('SELECT id, role_name, permissions FROM roles WHERE id = ? LIMIT 1');
        $editStmt->bind_param('i', $editId);
        $editStmt->execute();
        $editRole = $editStmt->get_result()->fetch_assoc();
        $editStmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roleId = (int) ($_POST['role_id'] ?? 0);
    $roleName = trim($_POST['role_name'] ?? '');
    $selectedPermissions = $_POST['permissions'] ?? [];

    $selectedPermissions = array_values(array_intersect(array_keys($permissionsMap), $selectedPermissions));

    if ($roleName === '') {
        set_flash('danger', 'Le nom du role est obligatoire.');
        redirect('roles.php');
    }

    if ($selectedPermissions === []) {
        set_flash('danger', 'Selectionnez au moins une permission.');
        redirect('roles.php');
    }

    $permissionsJson = json_encode($selectedPermissions, JSON_UNESCAPED_UNICODE);

    if ($roleId > 0) {
        $stmt = $conn->prepare('UPDATE roles SET role_name = ?, permissions = ? WHERE id = ?');
        $stmt->bind_param('ssi', $roleName, $permissionsJson, $roleId);
        $successMessage = 'Role mis a jour avec succes.';
    } else {
        $stmt = $conn->prepare('INSERT INTO roles (role_name, permissions) VALUES (?, ?)');
        $stmt->bind_param('ss', $roleName, $permissionsJson);
        $successMessage = 'Role ajoute avec succes.';
    }

    if ($stmt->execute()) {
        set_flash('success', $successMessage);
    } else {
        set_flash('danger', 'Impossible d enregistrer ce role. Le nom existe peut-etre deja.');
    }

    $stmt->close();
    redirect('roles.php');
}

$roles = $conn->query('SELECT id, role_name, permissions, created_at FROM roles ORDER BY role_name ASC');

require __DIR__ . '/partials/header.php';
?>
<?php $selectedEditPermissions = $editRole ? (json_decode((string) $editRole['permissions'], true) ?: []) : []; ?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card stat-card">
            <div class="card-header bg-white border-0 pt-4">
                <h1 class="h4 mb-0"><?= $editRole ? 'Modifier le role' : 'Nouveau role' ?></h1>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="role_id" value="<?= $editRole ? (int) $editRole['id'] : 0 ?>">
                    <div class="mb-3">
                        <label class="form-label" for="role_name">Nom du role</label>
                        <input class="form-control" id="role_name" name="role_name" value="<?= e($editRole['role_name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-block">Permissions</label>
                        <?php foreach ($permissionsMap as $permissionKey => $permissionLabel): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= e($permissionKey) ?>" id="<?= e($permissionKey) ?>" <?= in_array($permissionKey, $selectedEditPermissions, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="<?= e($permissionKey) ?>"><?= e($permissionLabel) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit"><?= $editRole ? 'Enregistrer' : 'Ajouter le role' ?></button>
                        <?php if ($editRole): ?>
                            <a class="btn btn-outline-secondary" href="roles.php">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card stat-card">
            <div class="card-header bg-white border-0 pt-4">
                <h2 class="h4 mb-0">Roles disponibles</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Role</th>
                                <th>Permissions</th>
                                <th>Creation</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($roles && $roles->num_rows > 0): ?>
                                <?php while ($role = $roles->fetch_assoc()): ?>
                                    <?php $rolePermissions = json_decode((string) $role['permissions'], true) ?: []; ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e($role['role_name']) ?></td>
                                        <td>
                                            <?php foreach ($rolePermissions as $permission): ?>
                                                <span class="badge text-bg-light border me-1 mb-1"><?= e($permissionsMap[$permission] ?? $permission) ?></span>
                                            <?php endforeach; ?>
                                        </td>
                                        <td><?= e($role['created_at']) ?></td>
                                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="roles.php?edit=<?= (int) $role['id'] ?>">Modifier</a></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted">Aucun role defini.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
