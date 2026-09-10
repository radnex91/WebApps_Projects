<?php
$pageTitle = 'Banques';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('settings_banks');

$db = getDB();

// CRUD Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'create') {
        $codeBank = cleanInput($_POST['codeBank'] ?? '');
        $nombank = cleanInput($_POST['nombank'] ?? '');

        if (empty($codeBank) || empty($nombank)) {
            setFlash('error', 'Le code et le nom sont obligatoires.');
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO bank (codeBank, nombank) VALUES (?,?)");
                $stmt->execute([$codeBank, $nombank]);
                addAuditLog('create', 'bank', $db->lastInsertId());
                setFlash('success', 'Banque créée avec succès.');
            } catch (Exception $e) { setFlash('error', 'Erreur : ' . $e->getMessage()); }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $codeBank = cleanInput($_POST['codeBank'] ?? '');
        $nombank = cleanInput($_POST['nombank'] ?? '');
        $statut = $_POST['statut'] ?? 'actif';

        try {
            $stmt = $db->prepare("UPDATE bank SET codeBank=?, nombank=?, statut=? WHERE id=?");
            $stmt->execute([$codeBank, $nombank, $statut, $id]);
            addAuditLog('update', 'bank', $id);
            setFlash('success', 'Banque modifiée.');
        } catch (Exception $e) { setFlash('error', 'Erreur : ' . $e->getMessage()); }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM bank WHERE id=?")->execute([$id]);
        addAuditLog('delete', 'bank', $id);
        setFlash('success', 'Banque supprimée.');
    }
    header('Location: banks.php'); exit;
}

$banks = $db->query("SELECT * FROM bank ORDER BY nombank")->fetchAll();
?>
<div class="main-content" id="main-content" role="main">
    <header class="main-header" role="banner"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button><span class="mb-0 fw-bold"><?php echo e($pageTitle); ?></span></div><div class="header-right"><div class="dropdown"><button class="notif-btn" aria-label="Notifications" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" role="button" tabindex="0" aria-label="Menu utilisateur" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-bank me-2"></i>Banques</h1></div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Nouvelle banque</button>
        </div>
        <div class="card"><div class="table-container">
            <table class="table" aria-label="Liste des banques">
                <thead><tr><th>Code</th><th>Nom</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($banks as $b): ?>
                <tr>
                    <td><code><?php echo e($b['codeBank']); ?></code></td>
                    <td class="fw-600"><?php echo e($b['nombank']); ?></td>
                    <td><span class="badge <?php echo $b['statut']==='actif'?'bg-success':'bg-secondary'; ?>"><?php echo e($b['statut']); ?></span></td>
                    <td><div class="action-btns">
                        <button class="btn btn-sm btn-outline-warning" onclick="editBank(<?php echo htmlspecialchars(json_encode($b)); ?>)"><i class="bi bi-pencil"></i></button>
                        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?php echo $b['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Supprimer" data-confirm="Supprimer cette banque ?"><i class="bi bi-trash"></i></button></form>
                    </div></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($banks)): ?><tr><td colspan="4" class="text-center text-muted py-4">Aucune banque</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="addModalLabel">Nouvelle banque</h5><button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="modal"></button></div>
    <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="form_action" value="create">
    <div class="modal-body">
        <div class="mb-3"><label for="codeBank" class="form-label">Code banque <span class="text-danger">*</span></label><input type="text" name="codeBank" id="codeBank" class="form-control" required placeholder="BICIGUI"></div>
        <div class="mb-3"><label for="nombank" class="form-label">Nom banque <span class="text-danger">*</span></label><input type="text" name="nombank" id="nombank" class="form-control" required></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Créer</button></div>
    </form>
</div></div></div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="editModalLabel">Modifier banque</h5><button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="modal"></button></div>
    <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="form_action" value="update"><input type="hidden" name="id" id="edit_id">
    <div class="modal-body">
        <div class="mb-3"><label for="codeBank" class="form-label">Code banque <span class="text-danger">*</span></label><input type="text" name="codeBank" id="codeBank" id="edit_codeBank" class="form-control" required></div>
        <div class="mb-3"><label for="nombank" class="form-label">Nom banque <span class="text-danger">*</span></label><input type="text" name="nombank" id="nombank" id="edit_nombank" class="form-control" required></div>
        <div class="mb-3"><label for="edit_statut" class="form-label">Statut</label><select name="statut" id="edit_statut" class="form-select"><option value="actif">Actif</option><option value="inactif">Inactif</option></select></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Modifier</button></div>
    </form>
</div></div></div>

<script>
function editBank(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_codeBank').value = data.codeBank;
    document.getElementById('edit_nombank').value = data.nombank;
    document.getElementById('edit_statut').value = data.statut;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>