<?php
$pageTitle = 'Clé de Répartition';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('settings_repartition');

$db = getDB();

// CRUD Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'create') {
        $proprietaire_id = (int)($_POST['proprietaire_id'] ?? 0);
        $part1 = (float)($_POST['part1'] ?? 0);
        $part2 = (float)($_POST['part2'] ?? 0);

        if (!$proprietaire_id || ($part1 + $part2) <= 0) {
            setFlash('error', 'Propriétaire et pourcentages sont obligatoires.');
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO cle_repartition (proprietaire_id, part1, part2) VALUES (?,?,?)");
                $stmt->execute([$proprietaire_id, $part1, $part2]);
                addAuditLog('create', 'cle_repartition', $db->lastInsertId());
                setFlash('success', 'Clé de répartition créée avec succès.');
            } catch (Exception $e) { setFlash('error', 'Erreur : ' . $e->getMessage()); }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $proprietaire_id = (int)($_POST['proprietaire_id'] ?? 0);
        $part1 = (float)($_POST['part1'] ?? 0);
        $part2 = (float)($_POST['part2'] ?? 0);

        try {
            $stmt = $db->prepare("UPDATE cle_repartition SET proprietaire_id=?, part1=?, part2=? WHERE id=?");
            $stmt->execute([$proprietaire_id, $part1, $part2, $id]);
            addAuditLog('update', 'cle_repartition', $id);
            setFlash('success', 'Clé de répartition modifiée.');
        } catch (Exception $e) { setFlash('error', 'Erreur : ' . $e->getMessage()); }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM cle_repartition WHERE id=?")->execute([$id]);
        addAuditLog('delete', 'cle_repartition', $id);
        setFlash('success', 'Clé de répartition supprimée.');
    }
    header('Location: repartition.php'); exit;
}

$cles = $db->query("SELECT cr.*, p.nomProprio, p.codeproprio FROM cle_repartition cr LEFT JOIN proprietaire p ON cr.proprietaire_id=p.id ORDER BY p.nomProprio")->fetchAll();
$proprietaires = getProprietaires($db);
?>
<div class="main-content" id="main-content" role="main">
    <header class="main-header" role="banner"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button><span class="mb-0 fw-bold"><?php echo e($pageTitle); ?></span></div><div class="header-right"><div class="dropdown"><button class="notif-btn" aria-label="Notifications" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" role="button" tabindex="0" aria-label="Menu utilisateur" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-pie-chart me-2"></i>Clé de Répartition</h1></div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Nouvelle clé</button>
        </div>
        <div class="card"><div class="table-container">
            <table class="table" aria-label="Clés de répartition">
                <thead><tr><th>Propriétaire</th><th>Part 1 (%)</th><th>Part 2 (%)</th><th>Total</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($cles as $c): ?>
                <tr>
                    <td class="fw-600"><?php echo e($c['nomProprio']); ?> <small class="text-muted">(<?php echo e($c['codeproprio']); ?>)</small></td>
                    <td><?php echo $c['part1']; ?>%</td>
                    <td><?php echo $c['part2']; ?>%</td>
                    <td class="fw-bold"><?php echo $c['part1'] + $c['part2']; ?>%</td>
                    <td><div class="action-btns">
                        <button class="btn btn-sm btn-outline-warning" onclick="editCle(<?php echo htmlspecialchars(json_encode($c)); ?>)"><i class="bi bi-pencil"></i></button>
                        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?php echo $c['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Supprimer" data-confirm="Supprimer cette clé ?"><i class="bi bi-trash"></i></button></form>
                    </div></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($cles)): ?><tr><td colspan="5" class="text-center text-muted py-4">Aucune clé de répartition</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="addModalLabel">Nouvelle clé de répartition</h5><button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="modal"></button></div>
    <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="form_action" value="create">
    <div class="modal-body">
        <div class="mb-3"><label for="proprietaire_id" class="form-label">Propriétaire <span class="text-danger">*</span></label><select name="proprietaire_id" id="proprietaire_id" class="form-select" required><option value="">-- Sélectionner --</option><?php foreach($proprietaires as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo e($p['nomProprio']); ?> (<?php echo e($p['codeproprio']); ?>)</option><?php endforeach; ?></select></div>
        <div class="mb-3"><label for="part1" class="form-label">Part 1 (%) <span class="text-danger">*</span></label><input type="number" name="part1" id="part1" class="form-control" min="0" max="100" step="0.01" required></div>
        <div class="mb-3"><label for="part2" class="form-label">Part 2 (%) <span class="text-danger">*</span></label><input type="number" name="part2" id="part2" class="form-control" min="0" max="100" step="0.01" required></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Créer</button></div>
    </form>
</div></div></div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="editModalLabel">Modifier clé de répartition</h5><button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="modal"></button></div>
    <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="form_action" value="update"><input type="hidden" name="id" id="edit_id">
    <div class="modal-body">
        <div class="mb-3"><label for="edit_proprietaire_id" class="form-label">Propriétaire <span class="text-danger">*</span></label><select name="proprietaire_id" id="edit_proprietaire_id" class="form-select" required><option value="">-- Sélectionner --</option><?php foreach($proprietaires as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo e($p['nomProprio']); ?> (<?php echo e($p['codeproprio']); ?>)</option><?php endforeach; ?></select></div>
        <div class="mb-3"><label for="edit_part1" class="form-label">Part 1 (%) <span class="text-danger">*</span></label><input type="number" name="part1" id="edit_part1" class="form-control" min="0" max="100" step="0.01" required></div>
        <div class="mb-3"><label for="edit_part2" class="form-label">Part 2 (%) <span class="text-danger">*</span></label><input type="number" name="part2" id="edit_part2" class="form-control" min="0" max="100" step="0.01" required></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Modifier</button></div>
    </form>
</div></div></div>

<script>
function editCle(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_proprietaire_id').value = data.proprietaire_id;
    document.getElementById('edit_part1').value = data.part1;
    document.getElementById('edit_part2').value = data.part2;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>