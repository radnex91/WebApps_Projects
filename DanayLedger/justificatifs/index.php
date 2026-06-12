<?php
$pageTitle = 'Pièces Justificatives';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('justificatifs');

$db = getDB();
$page = max(1, (int)($_GET['page'] ?? 1));
$filterEntity = $_GET['entity_type'] ?? '';

$where = $filterEntity ? "WHERE j.entity_type = ?" : "";
$params = $filterEntity ? [$filterEntity] : [];

$query = "SELECT j.*, u.full_name as uploader_name FROM justificatifs j LEFT JOIN users u ON j.uploaded_by=u.id $where ORDER BY j.created_at DESC";
$result = paginate($db, $query, $params, $page);

$entityLabels = ['recette'=>'Recette','depense'=>'Dépense','recette_camion'=>'Recette camion','versement'=>'Versement'];

// Upload justificatif
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['justificatif']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $entityType = cleanInput($_POST['entity_type'] ?? '');
    $entityId = (int)($_POST['entity_id'] ?? 0);
    $file = $_FILES['justificatif'];

    if ($entityType && $entityId && $file['error'] === UPLOAD_ERR_OK) {
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            setFlash('error', 'Fichier trop volumineux (max 5 MB).');
        } elseif (!in_array($file['type'], UPLOAD_ALLOWED_TYPES)) {
            setFlash('error', 'Type de fichier non autorisé.');
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $entityType . '_' . $entityId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            $filepath = UPLOAD_DIR . $filename;

            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $stmt = $db->prepare("INSERT INTO justificatifs (entity_type, entity_id, nom_fichier, chemin_fichier, type_mime, taille, uploaded_by) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$entityType, $entityId, $file['name'], $filename, $file['type'], $file['size'], $_SESSION['user_id']]);
                addAuditLog('create', 'justificatif', $db->lastInsertId());
                setFlash('success', 'Justificatif ajouté avec succès.');
            } else {
                setFlash('error', 'Erreur lors du téléchargement.');
            }
        }
    }
    header('Location: index.php');
    exit;
}

// Supprimer justificatif
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM justificatifs WHERE id = ?");
    $stmt->execute([$id]);
    $j = $stmt->fetch();
    if ($j) {
        $fullPath = UPLOAD_DIR . $j['chemin_fichier'];
        if (file_exists($fullPath)) unlink($fullPath);
        $db->prepare("DELETE FROM justificatifs WHERE id = ?")->execute([$id]);
        addAuditLog('delete', 'justificatif', $id);
        setFlash('success', 'Justificatif supprimé.');
    }
    header('Location: index.php');
    exit;
}
?>
<div class="main-content" id="main-content" role="main">
    <header class="main-header" role="banner"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button><span class="mb-0 fw-bold"><?php echo e($pageTitle); ?></span></div><div class="header-right"><div class="dropdown"><button class="notif-btn" aria-label="Notifications" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" role="button" tabindex="0" aria-label="Menu utilisateur" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-paperclip me-2"></i>Pièces Justificatives</h1></div>
            <?php if (hasPermission('justificatifs_upload')): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="bi bi-upload me-1"></i>Ajouter un justificatif</button>
            <?php endif; ?>
        </div>

        <div class="card mb-3"><div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label">Type d'entité</label><select name="entity_type" class="form-select"><option value="">Tous</option><?php foreach($entityLabels as $k=>$l): ?><option value="<?php echo $k; ?>" <?php echo $filterEntity===$k?'selected':''; ?>><?php echo e($l); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><button type="submit" class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Filtrer</button></div>
            </form>
        </div></div>

        <div class="card"><div class="table-container">
            <table class="table" aria-label="Liste des justificatifs">
                <thead><tr><th>Fichier</th><th>Type</th><th>ID Entité</th><th>Taille</th><th>Téléversé par</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($result['data'] as $j): ?>
                <tr>
                    <td><i class="bi <?php echo strpos($j['type_mime'],'pdf')!==false?'bi-file-earmark-pdf text-danger':'bi-file-earmark-image text-primary'; ?> me-1"></i><?php echo e($j['nom_fichier']); ?></td>
                    <td><span class="badge bg-info"><?php echo e($entityLabels[$j['entity_type']] ?? $j['entity_type']); ?></span></td>
                    <td><?php echo $j['entity_id']; ?></td>
                    <td><?php echo number_format($j['taille']/1024, 1); ?> KB</td>
                    <td><?php echo e($j['uploader_name'] ?? '-'); ?></td>
                    <td><?php echo formatDate($j['created_at']); ?></td>
                    <td><div class="action-btns">
                        <a href="<?php echo APP_URL; ?>/uploads/justificatifs/<?php echo e($j['chemin_fichier']); ?>" target="_blank" class="btn btn-sm btn-outline-primary" aria-label="Voir"><i class="bi bi-eye"></i></a>
                        <a href="<?php echo APP_URL; ?>/uploads/justificatifs/<?php echo e($j['chemin_fichier']); ?>" download class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i></a>
                        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $j['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Supprimer" data-confirm="Supprimer ce justificatif ?"><i class="bi bi-trash"></i></button></form>
                    </div></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($result['data'])): ?><tr><td colspan="7" class="text-center text-muted py-4">Aucun justificatif</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
        <?php echo renderPagination($result['page'], $result['totalPages'], '?entity_type=' . $filterEntity); ?>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="uploadModalLabel">Ajouter un justificatif</h5><button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="modal"></button></div>
    <form method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Type d'entité <span class="text-danger">*</span></label>
            <select name="entity_type" class="form-select" required><option value="">-- Sélectionner --</option><?php foreach($entityLabels as $k=>$l): ?><option value="<?php echo $k; ?>"><?php echo e($l); ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">ID de l'entité <span class="text-danger">*</span></label><input type="number" name="entity_id" class="form-control" min="1" required></div>
        <div class="mb-3"><label class="form-label">Fichier <span class="text-danger">*</span></label><input type="file" name="justificatif" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required><small class="text-muted">PDF, JPG, PNG - Max 5 MB</small></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Téléverser</button></div>
    </form>
</div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>