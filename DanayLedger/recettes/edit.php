<?php
$pageTitle = 'Modifier Recette';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('recettes_edit');

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM recette WHERE id = ?");
$stmt->execute([$id]);
$recette = $stmt->fetch();
if (!$recette || $recette['statut'] !== 'en_attente') redirectWithMessage(APP_URL . '/recettes/', 'error', 'Recette introuvable ou déjà traitée.');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { $error = 'Token invalide.'; }
    else {
        $oldValues = $recette;
        $date = $_POST['date'] ?? '';
        $montantexpedition = (float)($_POST['montantexpedition'] ?? 0);
        $montantAccompagnement = (float)($_POST['montantAccompagnement'] ?? 0);
        $agence_id = (int)($_POST['agence_id'] ?? 0) ?: null;
        $nomediteur = $_SESSION['full_name'] ?? '';
        $dateedite = date('Y-m-d');
        $numerorecu = cleanInput($_POST['numerorecu'] ?? '');
        $destination = cleanInput($_POST['destination'] ?? '');
        $guichetier = cleanInput($_POST['guichetier'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');

        if (empty($date) || ($montantexpedition + $montantAccompagnement) <= 0) { $error = 'La date et au moins un montant sont obligatoires.'; }
        else {
            try {
                $stmt = $db->prepare("UPDATE recette SET date=?, montantexpedition=?, montantAccompagnement=?, agence_id=?, nomediteur=?, dateedite=?, numerorecu=?, destination=?, guichetier=?, description=? WHERE id=?");
                $stmt->execute([$date, $montantexpedition, $montantAccompagnement, $agence_id, $nomediteur, $dateedite, $numerorecu, $destination, $guichetier, $description, $id]);
                if (isset($_FILES['justificatif']) && $_FILES['justificatif']['error'] === UPLOAD_ERR_OK) {
                    $path = uploadJustificatif($_FILES['justificatif'], 'recette', $id);
                    if ($path) $db->prepare("UPDATE recette SET justificatif_path=? WHERE id=?")->execute([$path, $id]);
                }
                addAuditLog('update', 'recette', $id, $oldValues, ['date'=>$date,'montantexpedition'=>$montantexpedition,'montantAccompagnement'=>$montantAccompagnement]);
                redirectWithMessage(APP_URL . '/recettes/', 'success', 'Recette modifiée.');
            } catch (Exception $e) { $error = 'Erreur : ' . $e->getMessage(); }
        }
    }
}
$agences = getAgences($db);
?>
<div class="main-content">
    <header class="main-header"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button><h6 class="mb-0 fw-bold"><?php echo e($pageTitle); ?></h6></div><div class="header-right"><div class="dropdown"><button class="notif-btn" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?php echo e($error); ?></div><?php endif; ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-pencil me-2"></i>Modifier Recette</h1><p class="page-subtitle">Réf : <?php echo e($recette['reference']); ?></p></div><a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>
        <div class="card"><div class="card-body">
            <form method="POST" enctype="multipart/form-data" data-validate>
                <?php echo csrfField(); ?>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Date <span class="text-danger">*</span></label><input type="date" name="date" class="form-control" value="<?php echo e($recette['date']); ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Montant Expédition (FCFA) <span class="text-danger">*</span></label><input type="number" name="montantexpedition" class="form-control" min="0" step="1" value="<?php echo $recette['montantexpedition']; ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Montant Accompagnement (FCFA)</label><input type="number" name="montantAccompagnement" class="form-control" min="0" step="1" value="<?php echo $recette['montantAccompagnement']; ?>"></div>
                    <div class="col-md-6"><label class="form-label">Agence <span class="text-danger">*</span></label><select name="agence_id" class="form-select" required><option value="">-- Sélectionner --</option><?php foreach($agences as $a): ?><option value="<?php echo $a['id']; ?>" <?php echo $recette['agence_id']==$a['id']?'selected':''; ?>><?php echo e($a['nomagence']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Numéro de reçu</label><input type="text" name="numerorecu" class="form-control" value="<?php echo e($recette['numerorecu'] ?? ''); ?>"></div>
                    <div class="col-md-6"><label class="form-label">Opérateur de saisie</label><input type="text" class="form-control" value="<?php echo e($recette['nomoperateur'] ?? ''); ?>" readonly style="background-color:#e9ecef;"></div>
                    <div class="col-md-6"><label class="form-label">Éditeur</label><input type="text" class="form-control" value="<?php echo e($_SESSION['full_name'] ?? ''); ?>" readonly style="background-color:#e9ecef;"></div>
                    <div class="col-md-4"><label class="form-label">Date édition</label><input type="text" class="form-control" value="<?php echo date('d/m/Y'); ?>" readonly style="background-color:#e9ecef;"></div>
                    <div class="col-md-4"><label class="form-label">Destination</label><input type="text" name="destination" class="form-control" value="<?php echo e($recette['destination'] ?? ''); ?>"></div>
                    <div class="col-md-4"><label class="form-label">Guichetier</label><input type="text" name="guichetier" class="form-control" value="<?php echo e($recette['guichetier'] ?? ''); ?>"></div>
                    <div class="col-md-6"><label class="form-label">Nouveau justificatif</label><input type="file" name="justificatif" class="form-control" accept=".pdf,.jpg,.jpeg,.png"><?php if($recette['justificatif_path']): ?><small class="text-muted">Actuel : <?php echo e($recette['justificatif_path']); ?></small><?php endif; ?></div>
                    <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"><?php echo e($recette['description'] ?? ''); ?></textarea></div>
                    <div class="col-12"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button><a href="index.php" class="btn btn-outline-secondary ms-2">Annuler</a></div>
                </div>
            </form>
        </div></div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>