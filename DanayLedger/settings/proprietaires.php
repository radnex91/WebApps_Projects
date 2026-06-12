<?php
$pageTitle = 'Propriétaires';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('settings_proprietaires');

$db = getDB();

// CRUD Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'create') {
        $codeproprio = cleanInput($_POST['codeproprio'] ?? '');
        $nomProprio = cleanInput($_POST['nomProprio'] ?? '');
        $groupe = cleanInput($_POST['groupe'] ?? '');

        if (empty($codeproprio) || empty($nomProprio)) {
            setFlash('error', 'Le code et le nom sont obligatoires.');
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO proprietaire (codeproprio, nomProprio, groupe) VALUES (?,?,?)");
                $stmt->execute([$codeproprio, $nomProprio, $groupe]);
                addAuditLog('create', 'proprietaire', $db->lastInsertId());
                setFlash('success', 'Propriétaire créé avec succès.');
            } catch (Exception $e) { setFlash('error', 'Erreur : ' . $e->getMessage()); }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $codeproprio = cleanInput($_POST['codeproprio'] ?? '');
        $nomProprio = cleanInput($_POST['nomProprio'] ?? '');
        $groupe = cleanInput($_POST['groupe'] ?? '');
        $statut = $_POST['statut'] ?? 'actif';

        try {
            $stmt = $db->prepare("UPDATE proprietaire SET codeproprio=?, nomProprio=?, groupe=?, statut=? WHERE id=?");
            $stmt->execute([$codeproprio, $nomProprio, $groupe, $statut, $id]);
            addAuditLog('update', 'proprietaire', $id);
            setFlash('success', 'Propriétaire modifié.');
        } catch (Exception $e) { setFlash('error', 'Erreur : ' . $e->getMessage()); }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM proprietaire WHERE id=?")->execute([$id]);
        addAuditLog('delete', 'proprietaire', $id);
        setFlash('success', 'Propriétaire supprimé.');
    }
    header('Location: proprietaires.php'); exit;
}

$proprietaires = $db->query("SELECT * FROM proprietaire ORDER BY nomProprio")->fetchAll();
?>
<div class="main-content" id="main-content" role="main">
    <header class="main-header" role="banner"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button><span class="mb-0 fw-bold"><?php echo e($pageTitle); ?></span></div><div class="header-right"><div class="dropdown"><button class="notif-btn" aria-label="Notifications" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" role="button" tabindex="0" aria-label="Menu utilisateur" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-people me-2"></i>Propriétaires</h1></div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Nouveau propriétaire</button>
        </div>
        <div class="card"><div class="table-container">
            <table class="table" aria-label="Liste des propriétaires">
                <thead><tr><th>Code</th><th>Nom</th><th>Groupe</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($proprietaires as $p): ?>
                <tr>
                    <td><code><?php echo e($p['codeproprio']); ?></code></td>
                    <td class="fw-600"><?php echo e($p['nomProprio']); ?></td>
                    <td><?php echo e($p['groupe'] ?? '-'); ?></td>
                    <td><span class="badge <?php echo $p['statut']==='actif'?'bg-success':'bg-secondary'; ?>"><?php echo e($p['statut']); ?></span></td>
                    <td><div class="action-btns">
                        <button class="btn btn-sm btn-outline-warning" onclick="editProprietaire(<?php echo htmlspecialchars(json_encode($p)); ?>)"><i class="bi bi-pencil"></i></button>
                        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?php echo $p['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Supprimer" data-confirm="Supprimer ce propriétaire ?"><i class="bi bi-trash"></i></button></form>
                    </div></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($proprietaires)): ?><tr><td colspan="5" class="text-center text-muted py-4">Aucun propriétaire</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="addModalLabel">Nouveau propriétaire</h5><button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="modal"></button></div>
    <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="form_action" value="create">
    <div class="modal-body">
        <div class="mb-3"><label for="codeproprio" class="form-label">Code <span class="text-danger">*</span></label><input type="text" name="codeproprio" id="codeproprio" class="form-control" required placeholder="PROP-001"></div>
        <div class="mb-3"><label for="nomProprio" class="form-label">Nom <span class="text-danger">*</span></label><input type="text" name="nomProprio" id="nomProprio" class="form-control" required></div>
        <div class="mb-3"><label for="groupe" class="form-label">Groupe</label><input type="text" name="groupe" id="groupe" class="form-control"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Créer</button></div>
    </form>
</div></div></div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="editModalLabel">Modifier propriétaire</h5><button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="modal"></button></div>
    <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="form_action" value="update"><input type="hidden" name="id" id="edit_id">
    <div class="modal-body">
        <div class="mb-3"><label for="codeproprio" class="form-label">Code <span class="text-danger">*</span></label><input type="text" name="codeproprio" id="codeproprio" id="edit_codeproprio" class="form-control" required></div>
        <div class="mb-3"><label for="nomProprio" class="form-label">Nom <span class="text-danger">*</span></label><input type="text" name="nomProprio" id="nomProprio" id="edit_nomProprio" class="form-control" required></div>
        <div class="mb-3"><label for="groupe" class="form-label">Groupe</label><input type="text" name="groupe" id="groupe" id="edit_groupe" class="form-control"></div>
        <div class="mb-3"><label for="edit_statut" class="form-label">Statut</label><select name="statut" id="edit_statut" class="form-select"><option value="actif">Actif</option><option value="inactif">Inactif</option></select></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Modifier</button></div>
    </form>
</div></div></div>

<script>
function editProprietaire(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_codeproprio').value = data.codeproprio;
    document.getElementById('edit_nomProprio').value = data.nomProprio;
    document.getElementById('edit_groupe').value = data.groupe || '';
    document.getElementById('edit_statut').value = data.statut;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>