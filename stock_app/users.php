<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

require_permission('manage_users');

$editUser = null;

if (isset($_GET['edit'])) {
    $editId = (int) ($_GET['edit'] ?? 0);
    if ($editId > 0) {
        $editStmt = $conn->prepare(
            'SELECT id, full_name, username, role_id, is_active
             FROM users
             WHERE id = ?
             LIMIT 1'
        );
        $editStmt->bind_param('i', $editId);
        $editStmt->execute();
        $editUser = $editStmt->get_result()->fetch_assoc();
        $editStmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $roleId = (int) ($_POST['role_id'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($fullName === '' || $username === '' || $roleId <= 0 || ($userId === 0 && $password === '')) {
        set_flash('danger', 'Veuillez remplir correctement le formulaire utilisateur.');
        redirect('users.php');
    }

    if ($userId > 0) {
        if ($password !== '') {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                'UPDATE users SET full_name = ?, username = ?, password = ?, role_id = ?, is_active = ? WHERE id = ?'
            );
            $stmt->bind_param('sssiii', $fullName, $username, $hashedPassword, $roleId, $isActive, $userId);
        } else {
            $stmt = $conn->prepare(
                'UPDATE users SET full_name = ?, username = ?, role_id = ?, is_active = ? WHERE id = ?'
            );
            $stmt->bind_param('ssiii', $fullName, $username, $roleId, $isActive, $userId);
        }
        $successMessage = 'Utilisateur mis a jour avec succes.';
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO users (full_name, username, password, role_id, is_active) VALUES (?, ?, ?, ?, 1)');
        $stmt->bind_param('sssi', $fullName, $username, $hashedPassword, $roleId);
        $successMessage = 'Utilisateur cree avec succes.';
    }

    if ($stmt->execute()) {
        set_flash('success', $successMessage);
    } else {
        set_flash('danger', 'Impossible d enregistrer cet utilisateur. Le nom d utilisateur existe peut-etre deja.');
    }

    $stmt->close();
    redirect('users.php');
}

$roles = $conn->query('SELECT id, role_name FROM roles ORDER BY role_name ASC');
$users = $conn->query(
    'SELECT u.id, u.full_name, u.username, u.role_id, u.is_active, u.created_at, r.role_name
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     ORDER BY u.created_at DESC'
);

require __DIR__ . '/partials/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card stat-card">
            <div class="card-header bg-white border-0 pt-4">
                <h1 class="h4 mb-0"><?= $editUser ? 'Modifier l utilisateur' : 'Nouvel utilisateur' ?></h1>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="user_id" value="<?= (int) ($editUser['id'] ?? 0) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="full_name">Nom complet</label>
                        <input class="form-control" id="full_name" name="full_name" value="<?= e($editUser['full_name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="username">Nom d utilisateur</label>
                        <input class="form-control" id="username" name="username" value="<?= e($editUser['username'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Mot de passe</label>
                        <input class="form-control" id="password" name="password" type="password" <?= $editUser ? '' : 'required' ?>>
                        <?php if ($editUser): ?>
                            <div class="form-text">Laisser vide pour conserver le mot de passe actuel.</div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="role_id">Role</label>
                        <select class="form-select" id="role_id" name="role_id" required>
                            <option value="">Choisir</option>
                            <?php if ($roles): ?>
                                <?php while ($role = $roles->fetch_assoc()): ?>
                                    <option value="<?= (int) $role['id'] ?>" <?= (int) ($editUser['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>><?= e($role['role_name']) ?></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <?php if ($editUser): ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= (int) ($editUser['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">Compte actif</label>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit"><?= $editUser ? 'Enregistrer' : 'Ajouter' ?></button>
                        <?php if ($editUser): ?>
                            <a class="btn btn-outline-secondary" href="users.php">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card stat-card">
            <div class="card-header bg-white border-0 pt-4">
                <h2 class="h4 mb-0">Comptes utilisateurs</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Identifiant</th>
                                <th>Role</th>
                                <th>Statut</th>
                                <th>Creation</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users && $users->num_rows > 0): ?>
                                <?php while ($userRow = $users->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e($userRow['full_name']) ?></td>
                                        <td><?= e($userRow['username']) ?></td>
                                        <td><?= e($userRow['role_name']) ?></td>
                                        <td>
                                            <span class="badge <?= (int) $userRow['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                                <?= (int) $userRow['is_active'] === 1 ? 'Actif' : 'Inactif' ?>
                                            </span>
                                        </td>
                                        <td><?= e($userRow['created_at']) ?></td>
                                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="users.php?edit=<?= (int) $userRow['id'] ?>">Modifier</a></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-muted">Aucun utilisateur.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
