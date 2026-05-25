<?php
$pageTitle = 'Gestion des rôles';
require_once __DIR__ . '/../includes/header.php';
Auth::requirePermission('parametres');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_role') {
        $nom = trim($_POST['nom'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $niveau = intval($_POST['niveau'] ?? 0);

        if ($nom) {
            try {
                Database::execute(
                    "INSERT INTO roles (nom, description, niveau) VALUES (?, ?, ?)",
                    [$nom, $description, $niveau]
                );
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Rôle créé.'];
            } catch (Exception $e) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Rôle déjà existant.'];
            }
            header('Location: roles.php');
            exit;
        }
    }

    if ($action === 'delete_role') {
        $roleId = intval($_POST['role_id']);
        if ($roleId > 3) {
            Database::execute("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);
            Database::execute("DELETE FROM roles WHERE id = ?", [$roleId]);
            Database::execute("UPDATE utilisateurs SET role_id = 3 WHERE role_id = ?", [$roleId]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Rôle supprimé.'];
            header('Location: roles.php');
            exit;
        }
    }

    if ($action === 'update_role') {
        $roleId = intval($_POST['role_id']);
        $permissions = $_POST['permissions'] ?? [];

        Database::execute("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);

        foreach ($permissions as $permId) {
            Database::execute(
                "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                [$roleId, intval($permId)]
            );
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Permissions mises à jour.'];
        Auth::loadPermissions();
        header('Location: roles.php');
        exit;
    }
}

$roles = Database::fetchAll("SELECT * FROM roles ORDER BY niveau DESC");
$permissions = Database::fetchAll("SELECT * FROM permissions ORDER BY nom");

$rolePerms = [];
$rp = Database::fetchAll("SELECT role_id, permission_id FROM role_permissions");
foreach ($rp as $r) {
    $rolePerms[$r['role_id']][] = $r['permission_id'];
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <div>
        <h2 style="font-size:1.3rem;font-weight:700">Rôles & Permissions</h2>
        <p class="text-muted" style="font-size:.85rem"><?= count($roles) ?> rôles configurés</p>
    </div>
    <button class="btn btn-primary" data-modal="modal-add-role">
        <i class="fas fa-plus"></i> Nouveau rôle
    </button>
</div>

<!-- MODAL CREATE ROLE -->
<div class="modal-backdrop" id="modal-add-role">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Nouveau rôle</div>
            <button class="modal-close" onclick="this.closest('.modal-backdrop').classList.remove('open')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="create_role">
            <div class="form-group">
                <label class="form-label">Nom du rôle <span class="req">*</span></label>
                <input type="text" name="nom" class="form-control" required placeholder="ex: gestionnaire">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control" placeholder="Description du rôle">
            </div>
            <div class="form-group">
                <label class="form-label">Niveau (0-100)</label>
                <input type="number" name="niveau" class="form-control" value="0" min="0" max="100">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-backdrop').classList.remove('open')">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Créer</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($roles as $role): ?>
<div class="card" style="margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <div>
            <h3 style="font-size:1.1rem;font-weight:700;color:var(--primary)"><?= htmlspecialchars($role['nom']) ?></h3>
            <p style="font-size:.85rem;color:var(--text-muted)"><?= htmlspecialchars($role['description'] ?? '') ?></p>
        </div>
        <span class="badge badge-<?= $role['actif'] ? 'success' : 'danger' ?>">
            <?= $role['actif'] ? 'Actif' : 'Inactif' ?>
        </span>
        <?php if ($role['id'] > 3): ?>
        <form method="post" style="display:inline">
            <input type="hidden" name="action" value="delete_role">
            <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
            <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Supprimer ce rôle ?">
                <i class="fas fa-trash"></i>
            </button>
        </form>
        <?php endif; ?>
    </div>

    <form method="post">
        <input type="hidden" name="action" value="update_role">
        <input type="hidden" name="role_id" value="<?= $role['id'] ?>">

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:16px">
            <?php foreach ($permissions as $perm): ?>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="permissions[]" value="<?= $perm['id'] ?>"
                    <?= in_array($perm['id'], $rolePerms[$role['id']] ?? []) ? 'checked' : '' ?>>
                <span style="font-size:.9rem"><?= htmlspecialchars($perm['nom']) ?></span>
            </label>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save"></i> Sauvegarder
        </button>
    </form>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>