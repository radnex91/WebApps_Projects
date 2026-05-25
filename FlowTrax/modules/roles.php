<?php
requireLogin();

$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch ($action) {
    case 'delete':
        requirePermission('roles.delete');
        $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        $db->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$id]);
        $_SESSION['flash']['success'] = 'Rôle supprimé avec succès.';
        redirect('index.php?page=roles');
        break;

    case 'create':
    case 'edit':
        requirePermission($action === 'create' ? 'roles.create' : 'roles.edit');

        $role = ['nom'=>'', 'description'=>''];
        $permIds = [];
        if ($action === 'edit' && $id) {
            $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
            $stmt->execute([$id]);
            $role = $stmt->fetch();
            if (!$role) { $_SESSION['flash']['error'] = 'Rôle introuvable.'; redirect('index.php?page=roles'); }
            $stmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
            $stmt->execute([$id]);
            $permIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = ['nom' => $_POST['nom'], 'description' => $_POST['description']];
            $perms = $_POST['permissions'] ?? [];

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO roles (nom, description) VALUES (:nom, :description)");
                $stmt->execute($data);
                $roleId = $db->lastInsertId();
            } else {
                $stmt = $db->prepare("UPDATE roles SET nom=:nom, description=:description WHERE id=$id");
                $stmt->execute($data);
                $roleId = $id;
            }

            $db->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$roleId]);
            $insert = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($perms as $pid) {
                $insert->execute([$roleId, $pid]);
            }

            $_SESSION['flash']['success'] = 'Rôle enregistré avec succès.';
            redirect('index.php?page=roles');
        }

        $permissions = $db->query("SELECT * FROM permissions ORDER BY module, nom")->fetchAll();
        $grouped = [];
        foreach ($permissions as $p) {
            $grouped[$p['module']][] = $p;
        }
        ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $action === 'create' ? '🔐 Nouveau rôle' : '✏️ Modifier le rôle' ?></h3>
                <a href="index.php?page=roles" class="btn btn-sm btn-ghost">← Retour</a>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nom du rôle *</label>
                        <input type="text" name="nom" class="form-control" value="<?= e($role['nom']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" class="form-control" value="<?= e($role['description']) ?>">
                    </div>
                </div>

                <h4 style="margin:20px 0 12px;">Permissions</h4>
                <?php foreach ($grouped as $module => $perms): ?>
                <div style="margin-bottom:16px;">
                    <h5 style="font-size:13px;text-transform:uppercase;letter-spacing:0.5px;color:var(--gray-500);margin-bottom:8px;">📦 <?= e($module) ?></h5>
                    <div class="toggle-group">
                        <?php foreach ($perms as $p): ?>
                        <label class="toggle-item">
                            <input type="checkbox" name="permissions[]" value="<?= $p['id'] ?>" <?= in_array($p['id'], $permIds) ? 'checked' : '' ?>>
                            <span><?= e($p['nom']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="form-actions">
                    <a href="index.php?page=roles" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
                </div>
            </form>
        </div>
        <?php
        break;

    default:
        requirePermission('roles.view');
        $roles = $db->query("
            SELECT r.*, (SELECT COUNT(*) FROM users WHERE role_id = r.id) as nb_users
            FROM roles r ORDER BY r.nom
        ")->fetchAll();
        ?>
        <div class="actions-bar">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" class="table-search" placeholder="Rechercher un rôle...">
            </div>
            <?php if (hasPermission('roles.create')): ?>
            <a href="index.php?page=roles&action=create" class="btn btn-primary">➕ Nouveau rôle</a>
            <?php endif; ?>
        </div>

        <?php if ($success = flash('success')): ?><div class="alert alert-success">✅ <?= e($success) ?></div><?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🔐 Gestion des rôles</h3>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Rôle</th>
                            <th>Description</th>
                            <th>Utilisateurs</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $r): ?>
                        <tr>
                            <td><strong><?= e($r['nom']) ?></strong></td>
                            <td><?= e($r['description']) ?></td>
                            <td><span class="badge badge-info"><?= $r['nb_users'] ?></span></td>
                            <td>
                                <div class="btn-group">
                                    <?php if (hasPermission('roles.edit')): ?>
                                    <a href="index.php?page=roles&action=edit&id=<?= $r['id'] ?>" class="btn-icon" title="Modifier">✏️</a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('roles.delete') && $r['nb_users'] == 0): ?>
                                    <a href="index.php?page=roles&action=delete&id=<?= $r['id'] ?>" class="btn-icon" data-confirm="Supprimer ce rôle ?" title="Supprimer">🗑️</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        break;
}
