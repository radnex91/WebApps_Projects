<?php
// AJAX endpoint: load permissions for a role (must be before any HTML output)
if (isset($_GET['action']) && $_GET['action'] === 'get_permissions') {
    require_once __DIR__ . '/../config/database.php';
    $db = getDB();
    header('Content-Type: application/json');
    $roleId = (int)($_GET['role_id'] ?? 0);
    $stmt = $db->prepare("SELECT permission FROM role_permissions WHERE role_id = ?");
    $stmt->execute([$roleId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
    exit;
}

$pageTitle = 'Types d\'utilisateurs';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('settings_roles');

$db = getDB();
$currentLevel = getCurrentRoleLevel();

// Libellés lisibles pour les permissions
$permLabels = [
    'dashboard' => 'Tableau de bord',
    'users' => 'Voir les utilisateurs', 'users_create' => 'Créer un utilisateur', 'users_edit' => 'Modifier un utilisateur', 'users_delete' => 'Supprimer un utilisateur', 'users_toggle' => 'Activer/désactiver',
    'settings' => 'Paramètres', 'settings_agences' => 'Agences', 'settings_categories' => 'Catégories', 'settings_types' => 'Types d\'opérations',
    'settings_vehicules' => 'Véhicules', 'settings_params' => 'Paramètres système', 'settings_banks' => 'Banques', 'settings_proprietaires' => 'Propriétaires',
    'settings_repartition' => 'Clés de répartition', 'settings_roles' => 'Rôles & permissions',
    'recettes' => 'Voir les recettes', 'recettes_create' => 'Créer une recette', 'recettes_edit' => 'Modifier une recette', 'recettes_delete' => 'Supprimer une recette', 'recettes_validate' => 'Valider/annuler',
    'depenses' => 'Voir les dépenses', 'depenses_create' => 'Créer une dépense', 'depenses_edit' => 'Modifier une dépense', 'depenses_delete' => 'Supprimer une dépense', 'depenses_validate' => 'Valider/annuler',
    'recettes_camions' => 'Voir les recettes camions', 'recettes_camions_create' => 'Créer une recette camion', 'recettes_camions_edit' => 'Modifier', 'recettes_camions_delete' => 'Supprimer', 'recettes_camions_validate' => 'Valider/annuler',
    'versements' => 'Voir les versements', 'versements_create' => 'Créer un versement', 'versements_edit' => 'Modifier', 'versements_delete' => 'Supprimer', 'versements_validate' => 'Valider/annuler',
    'imports' => 'Importer des données', 'exports' => 'Exporter des données',
    'recherche' => 'Recherche globale',
    'rapports' => 'Voir les rapports', 'rapports_journalier' => 'Rapport journalier', 'rapports_hebdo' => 'Rapport hebdomadaire', 'rapports_mensuel' => 'Rapport mensuel', 'rapports_agence' => 'Rapport par agence', 'rapports_camion' => 'Rapport par camion',
    'rapprochement' => 'Voir les rapprochements', 'rapprochement_create' => 'Créer un rapprochement', 'rapprochement_validate' => 'Valider',
    'audit' => 'Journal d\'audit', 'sauvegarde' => 'Sauvegarder', 'sauvegarde_restore' => 'Restaurer',
    'notifications' => 'Notifications', 'justificatifs' => 'Voir les justificatifs', 'justificatifs_upload' => 'Téléverser un justificatif', 'impression' => 'Imprimer',
];

// Actions CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = cleanInput($_POST['name'] ?? '');
        $slug = cleanInput($_POST['slug'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');
        $level = (int)($_POST['level'] ?? 50);
        $permissions = $_POST['permissions'] ?? [];

        if (empty($name) || empty($slug)) {
            setFlash('error', 'Le nom est obligatoire.');
        } elseif ($level <= 0) {
            setFlash('error', 'Le niveau doit être un entier positif.');
        } elseif ($level < $currentLevel) {
            setFlash('error', 'Vous ne pouvez pas créer un rôle avec un niveau supérieur au vôtre.');
        } else {
            $stmt = $db->prepare("SELECT id FROM roles WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                setFlash('error', 'Ce slug existe déjà.');
            } else {
                try {
                    $db->prepare("INSERT INTO roles (name, slug, description, is_system, level) VALUES (?, ?, ?, 0, ?)")->execute([$name, $slug, $description, $level]);
                    $roleId = $db->lastInsertId();
                    $stmtPerm = $db->prepare("INSERT INTO role_permissions (role_id, permission) VALUES (?, ?)");
                    foreach ($permissions as $p) { $stmtPerm->execute([$roleId, $p]); }
                    addAuditLog('create', 'role', $roleId, [], ['name'=>$name,'slug'=>$slug,'level'=>$level]);
                    refreshSessionPermissions();
                    setFlash('success', 'Type d\'utilisateur créé.');
                } catch (Exception $e) { setFlash('error', 'Erreur : ' . $e->getMessage()); }
            }
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = cleanInput($_POST['name'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');
        $level = (int)($_POST['level'] ?? 50);
        $permissions = $_POST['permissions'] ?? [];

        $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        $role = $stmt->fetch();
        if (!$role) {
            setFlash('error', 'Type introuvable.');
        } elseif ($role['level'] < $currentLevel) {
            setFlash('error', 'Vous ne pouvez pas modifier un rôle de niveau supérieur au vôtre.');
        } elseif ($level < $currentLevel) {
            setFlash('error', 'Vous ne pouvez pas attribuer un niveau supérieur au vôtre.');
        } else {
            try {
                $db->prepare("UPDATE roles SET name=?, description=?, level=? WHERE id=?")->execute([$name, $description, $level, $id]);
                $db->prepare("DELETE FROM role_permissions WHERE role_id=?")->execute([$id]);
                $stmtPerm = $db->prepare("INSERT INTO role_permissions (role_id, permission) VALUES (?, ?)");
                foreach ($permissions as $p) { $stmtPerm->execute([$id, $p]); }
                addAuditLog('update', 'role', $id, ['name'=>$role['name']], ['name'=>$name]);
                refreshSessionPermissions();
                setFlash('success', 'Type d\'utilisateur modifié.');
            } catch (Exception $e) { setFlash('error', 'Erreur : ' . $e->getMessage()); }
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        $role = $stmt->fetch();
        if (!$role) {
            setFlash('error', 'Type introuvable.');
        } elseif ($role['is_system']) {
            setFlash('error', 'Les types système ne peuvent pas être supprimés.');
        } elseif ($role['level'] < $currentLevel) {
            setFlash('error', 'Vous ne pouvez pas supprimer un rôle de niveau supérieur au vôtre.');
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
            $stmt->execute([$role['slug']]);
            $userCount = (int) $stmt->fetchColumn();
            if ($userCount > 0) {
                setFlash('error', 'Ce type est assigné à ' . $userCount . ' utilisateur(s). Changez leur type avant de supprimer.');
            } else {
                $db->prepare("DELETE FROM roles WHERE id = ?")->execute([$id]);
                addAuditLog('delete', 'role', $id, ['name'=>$role['name'],'slug'=>$role['slug']]);
                setFlash('success', 'Type d\'utilisateur supprimé.');
            }
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

$roles = $db->query("SELECT r.*, (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS perm_count, (SELECT COUNT(*) FROM users u WHERE u.role = r.slug) AS user_count FROM roles r ORDER BY r.level ASC, r.name")->fetchAll();
$allPermissions = PERMISSION_GROUPS;
?>
<div class="main-content" id="main-content" role="main">
    <header class="main-header" role="banner">
        <div class="header-left"><button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button><span class="mb-0 fw-bold"><?php echo e($pageTitle); ?></span></div>
        <div class="header-right"><div class="dropdown"><button class="notif-btn" aria-label="Notifications" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" role="button" tabindex="0" aria-label="Menu utilisateur" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div>
    </header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header">
            <div><h1 class="page-title"><i class="bi bi-people me-2"></i>Types d'utilisateurs</h1><p class="page-subtitle">Définir les rôles et leurs accès</p></div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Nouveau type</button>
        </div>

        <div class="card">
            <div class="table-container">
                <table class="table" aria-label="Liste des rôles">
                    <thead><tr><th>Nom</th><th>Niveau d'accès</th><th>Permissions</th><th>Utilisateurs</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($roles as $r): ?>
                    <?php $canManage = ($r['level'] >= $currentLevel); ?>
                    <tr<?php if (!$canManage): ?> class="table-secondary"<?php endif; ?>>
                        <td class="fw-600"><?php echo e($r['name']); ?><?php if ($r['is_system']): ?> <span class="badge bg-warning text-dark ms-1">Système</span><?php endif; ?></td>
                        <td><span class="badge <?php echo $r['level'] <= 10 ? 'bg-danger' : ($r['level'] <= 50 ? 'bg-primary' : 'bg-secondary'); ?>"><?php echo $r['level']; ?></span></td>
                        <td><span class="badge bg-primary"><?php echo $r['perm_count']; ?> accès</span></td>
                        <td><span class="badge bg-secondary"><?php echo $r['user_count']; ?></span></td>
                        <td>
                            <div class="action-btns">
                                <?php if ($canManage): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary btn-edit-role" title="Modifier"
                                    data-id="<?php echo $r['id']; ?>"
                                    data-name="<?php echo e($r['name']); ?>"
                                    data-slug="<?php echo e($r['slug']); ?>"
                                    data-description="<?php echo e($r['description'] ?? ''); ?>"
                                    data-level="<?php echo $r['level']; ?>"
                                    data-system="<?php echo $r['is_system']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if (!$r['is_system'] && $r['user_count'] == 0): ?>
                                <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $r['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Supprimer" data-confirm="Supprimer ce type d'utilisateur ?" title="Supprimer"><i class="bi bi-trash"></i></button></form>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="text-muted small"><i class="bi bi-lock me-1"></i>Non modifiable</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($roles)): ?><tr><td colspan="5" class="text-center text-muted py-4">Aucun type trouvé</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Créer -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="addModalLabel"><i class="bi bi-people me-2"></i>Nouveau type d'utilisateur</h5><button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="modal"></button></div>
    <form method="POST" data-validate>
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Nom du type <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="create_name" class="form-control" required placeholder="Ex: Gestionnaire">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Niveau d'accès <span class="text-danger">*</span></label>
                    <input type="number" name="level" id="create_level" class="form-control" required min="<?php echo $currentLevel; ?>" value="<?php echo max($currentLevel, 50); ?>" placeholder="1 = plus élevé">
                    <small class="text-muted">Plus petit = plus de droits (votre niveau : <?php echo $currentLevel; ?>)</small>
                </div>
                <div class="col-12" style="display:none;">
                    <input type="text" name="slug" id="create_slug" class="form-control" value="">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" placeholder="Description du rôle...">
                </div>
            </div>
            <h6 class="border-bottom pb-2 mb-3"><i class="bi bi-key me-1"></i>Accès et permissions</h6>
            <div class="d-flex justify-content-end mb-2 gap-1">
                <button type="button" class="btn btn-sm btn-outline-primary" id="createSelectAll">Tout sélectionner</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="createDeselectAll">Tout déselectionner</button>
            </div>
            <ul class="nav nav-tabs flex-nowrap overflow-auto" id="createPermTabs" role="tablist">
                <?php $first = true; foreach ($allPermissions as $groupName => $groupPerms): $groupSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', str_replace(['é','è','ê','ë','à','â','ô','ù','û','î','ï','ç','É','È','Ê','Ë','À','Â','Ô','Ù','Û','Î','Ï','Ç'], ['e','e','e','e','a','a','o','u','u','i','i','c','e','e','e','e','a','a','o','u','u','i','i','c'], $groupName))); ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link<?php if ($first): ?> active<?php endif; ?>" id="create-tab-<?php echo $groupSlug; ?>" data-bs-toggle="tab" data-bs-target="#create-pane-<?php echo $groupSlug; ?>" type="button" role="tab" aria-controls="create-pane-<?php echo $groupSlug; ?>"<?php if ($first): ?> aria-selected="true"<?php endif; ?>><?php echo e($groupName); ?></button>
                </li>
                <?php $first = false; endforeach; ?>
            </ul>
            <div class="tab-content border border-top-0 rounded-bottom p-3 mb-0" style="min-height:180px;">
                <?php $first = true; foreach ($allPermissions as $groupName => $groupPerms): $groupSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', str_replace(['é','è','ê','ë','à','â','ô','ù','û','î','ï','ç','É','È','Ê','Ë','À','Â','Ô','Ù','Û','Î','Ï','Ç'], ['e','e','e','e','a','a','o','u','u','i','i','c','e','e','e','e','a','a','o','u','u','i','i','c'], $groupName))); ?>
                <div class="tab-pane fade<?php if ($first): ?> show active<?php endif; ?>" id="create-pane-<?php echo $groupSlug; ?>" role="tabpanel" aria-labelledby="create-tab-<?php echo $groupSlug; ?>">
                    <div class="d-flex justify-content-end mb-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-toggle-group py-0 px-2" data-group="<?php echo $groupSlug; ?>">Tout / Aucun</button>
                    </div>
                    <div class="row g-2">
                        <?php foreach ($groupPerms as $perm): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-check">
                                <input class="form-check-input perm-check" type="checkbox" name="permissions[]" value="<?php echo e($perm); ?>" id="create_perm_<?php echo e($perm); ?>" data-group="<?php echo $groupSlug; ?>">
                                <label class="form-check-label small" for="create_perm_<?php echo e($perm); ?>"><?php echo e($permLabels[$perm] ?? $perm); ?></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php $first = false; endforeach; ?>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Créer</button>
        </div>
    </form>
</div></div></div>

<!-- Modal Modifier -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="editModalLabel"><i class="bi bi-pencil me-2"></i>Modifier le type</h5><button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="modal"></button></div>
    <form method="POST" data-validate>
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-body">
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Nom du type <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Niveau d'accès <span class="text-danger">*</span></label>
                    <input type="number" name="level" id="edit_level" class="form-control" required min="<?php echo $currentLevel; ?>">
                    <small class="text-muted">Votre niveau : <?php echo $currentLevel; ?></small>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" id="edit_description" class="form-control" placeholder="Description du rôle...">
                </div>
            </div>
            <h6 class="border-bottom pb-2 mb-3"><i class="bi bi-key me-1"></i>Accès et permissions</h6>
            <div class="d-flex justify-content-end mb-2 gap-1">
                <button type="button" class="btn btn-sm btn-outline-primary" id="editSelectAll">Tout sélectionner</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="editDeselectAll">Tout déselectionner</button>
            </div>
            <ul class="nav nav-tabs flex-nowrap overflow-auto" id="editPermTabs" role="tablist">
                <?php $first = true; foreach ($allPermissions as $groupName => $groupPerms): $groupSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', str_replace(['é','è','ê','ë','à','â','ô','ù','û','î','ï','ç','É','È','Ê','Ë','À','Â','Ô','Ù','Û','Î','Ï','Ç'], ['e','e','e','e','a','a','o','u','u','i','i','c','e','e','e','e','a','a','o','u','u','i','i','c'], $groupName))); ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link<?php if ($first): ?> active<?php endif; ?>" id="edit-tab-<?php echo $groupSlug; ?>" data-bs-toggle="tab" data-bs-target="#edit-pane-<?php echo $groupSlug; ?>" type="button" role="tab" aria-controls="edit-pane-<?php echo $groupSlug; ?>"<?php if ($first): ?> aria-selected="true"<?php endif; ?>><?php echo e($groupName); ?></button>
                </li>
                <?php $first = false; endforeach; ?>
            </ul>
            <div class="tab-content border border-top-0 rounded-bottom p-3 mb-0" style="min-height:180px;">
                <?php $first = true; foreach ($allPermissions as $groupName => $groupPerms): $groupSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', str_replace(['é','è','ê','ë','à','â','ô','ù','û','î','ï','ç','É','È','Ê','Ë','À','Â','Ô','Ù','Û','Î','Ï','Ç'], ['e','e','e','e','a','a','o','u','u','i','i','c','e','e','e','e','a','a','o','u','u','i','i','c'], $groupName))); ?>
                <div class="tab-pane fade<?php if ($first): ?> show active<?php endif; ?>" id="edit-pane-<?php echo $groupSlug; ?>" role="tabpanel" aria-labelledby="edit-tab-<?php echo $groupSlug; ?>">
                    <div class="d-flex justify-content-end mb-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-toggle-group py-0 px-2" data-group="<?php echo $groupSlug; ?>">Tout / Aucun</button>
                    </div>
                    <div class="row g-2">
                        <?php foreach ($groupPerms as $perm): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-check">
                                <input class="form-check-input perm-check" type="checkbox" name="permissions[]" value="<?php echo e($perm); ?>" id="edit_perm_<?php echo e($perm); ?>" data-group="<?php echo $groupSlug; ?>">
                                <label class="form-check-label small" for="edit_perm_<?php echo e($perm); ?>"><?php echo e($permLabels[$perm] ?? $perm); ?></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php $first = false; endforeach; ?>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
        </div>
    </form>
</div></div></div>

<script>
// Auto-generate slug from name
document.getElementById('create_name')?.addEventListener('input', function() {
    const slug = this.value.toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9_]/g, '_')
        .replace(/_+/g, '_')
        .replace(/^_|_$/g, '');
    document.getElementById('create_slug').value = slug;
});

// Toggle group checkboxes (works inside tab panes)
document.querySelectorAll('.btn-toggle-group').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const pane = this.closest('.tab-pane');
        const checkboxes = pane.querySelectorAll('.perm-check');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
        this.textContent = allChecked ? 'Tout / Aucun' : 'Tout / Aucun';
    });
});

// Select all / Deselect all (both modals)
document.getElementById('createSelectAll')?.addEventListener('click', function() {
    document.querySelectorAll('#addModal .perm-check').forEach(cb => cb.checked = true);
});
document.getElementById('createDeselectAll')?.addEventListener('click', function() {
    document.querySelectorAll('#addModal .perm-check').forEach(cb => cb.checked = false);
});
document.getElementById('editSelectAll')?.addEventListener('click', function() {
    document.querySelectorAll('#editModal .perm-check').forEach(cb => cb.checked = true);
});
document.getElementById('editDeselectAll')?.addEventListener('click', function() {
    document.querySelectorAll('#editModal .perm-check').forEach(cb => cb.checked = false);
});

// Edit modal - populate fields and load permissions
document.querySelectorAll('.btn-edit-role').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const roleId = this.dataset.id;
        const roleName = this.dataset.name;
        const roleDesc = this.dataset.description;
        const roleLevel = this.dataset.level;

        document.getElementById('edit_id').value = roleId;
        document.getElementById('edit_name').value = roleName;
        document.getElementById('edit_description').value = roleDesc;
        document.getElementById('edit_level').value = roleLevel;

        // Reset all checkboxes
        document.querySelectorAll('#editModal .perm-check').forEach(cb => cb.checked = false);

        // Load permissions via AJAX
        fetch('<?php echo APP_URL; ?>/settings/roles.php?action=get_permissions&role_id=' + roleId, {
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(r => r.json())
        .then(perms => {
            perms.forEach(p => {
                const cb = document.getElementById('edit_perm_' + p);
                if (cb) cb.checked = true;
            });
        })
        .catch(() => {});

        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>