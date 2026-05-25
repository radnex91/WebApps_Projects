<?php
$pageTitle = 'Modifier Dépense';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('depenses_edit');

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM depenses WHERE id = ?");
$stmt->execute([$id]);
$depense = $stmt->fetch();
if (!$depense || $depense['statut'] !== 'en_attente') redirectWithMessage(APP_URL . '/depenses/', 'error', 'Dépense introuvable ou déjà traitée.');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { $error = 'Token invalide.'; }
    else {
        $oldValues = $depense;
        $date_depense = $_POST['date_depense'] ?? '';
        $montant = (float)($_POST['montant'] ?? 0);
        $categorie_id = (int)($_POST['categorie_id'] ?? 0) ?: null;
        $type_operation_id = (int)($_POST['type_operation_id'] ?? 0) ?: null;
        $agence_id = (int)($_POST['agence_id'] ?? 0) ?: null;
        $vehicule_id = (int)($_POST['vehicule_id'] ?? 0) ?: null;
        $nature = cleanInput($_POST['nature'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');

        if (empty($date_depense) || $montant <= 0) { $error = 'La date et le montant sont obligatoires.'; }
        else {
            try {
                $stmt = $db->prepare("UPDATE depenses SET date_depense=?, montant=?, categorie_id=?, type_operation_id=?, agence_id=?, vehicule_id=?, nature=?, description=? WHERE id=?");
                $stmt->execute([$date_depense, $montant, $categorie_id, $type_operation_id, $agence_id, $vehicule_id, $nature, $description, $id]);
                if (isset($_FILES['justificatif']) && $_FILES['justificatif']['error'] === UPLOAD_ERR_OK) {
                    $path = uploadJustificatif($_FILES['justificatif'], 'depense', $id);
                    if ($path) $db->prepare("UPDATE depenses SET justificatif_path=? WHERE id=?")->execute([$path, $id]);
                }
                addAuditLog('update', 'depense', $id, $oldValues, ['montant'=>$montant,'date'=>$date_depense]);
                redirectWithMessage(APP_URL . '/depenses/', 'success', 'Dépense modifiée.');
            } catch (Exception $e) { $error = 'Erreur : ' . $e->getMessage(); }
        }
    }
}
$agences = getAgences($db); $categories = getCategories($db, 'depense'); $vehicules = getVehicules($db);
$typesOps = $db->query("SELECT id, code, nom FROM types_operations WHERE statut='actif' ORDER BY nom")->fetchAll();
?>
<div class="main-content">
    <header class="main-header"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button><h6 class="mb-0 fw-bold"><?php echo e($pageTitle); ?></h6></div><div class="header-right"><div class="dropdown"><button class="notif-btn" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?php echo e($error); ?></div><?php endif; ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-pencil me-2"></i>Modifier Dépense</h1><p class="page-subtitle">Réf : <?php echo e($depense['reference']); ?></p></div><a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>
        <div class="card"><div class="card-body">
            <form method="POST" enctype="multipart/form-data" data-validate>
                <?php echo csrfField(); ?>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Date <span class="text-danger">*</span></label><input type="date" name="date_depense" class="form-control" value="<?php echo e($depense['date_depense']); ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Montant (FCFA) <span class="text-danger">*</span></label><input type="number" name="montant" class="form-control" min="0" step="1" value="<?php echo $depense['montant']; ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Catégorie</label><select name="categorie_id" class="form-select"><option value="">-- Sélectionner --</option><?php foreach($categories as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo $depense['categorie_id']==$c['id']?'selected':''; ?>><?php echo e($c['nom']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Type d'opération</label><select name="type_operation_id" class="form-select"><option value="">-- Sélectionner --</option><?php foreach($typesOps as $t): ?><option value="<?php echo $t['id']; ?>" <?php echo $depense['type_operation_id']==$t['id']?'selected':''; ?>><?php echo e($t['nom']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Agence</label><select name="agence_id" class="form-select"><option value="">-- Sélectionner --</option><?php foreach($agences as $a): ?><option value="<?php echo $a['id']; ?>" <?php echo $depense['agence_id']==$a['id']?'selected':''; ?>><?php echo e($a['nomagence']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Camion</label><select name="vehicule_id" class="form-select"><option value="">-- Aucun --</option><?php foreach($vehicules as $v): ?><option value="<?php echo $v['id']; ?>" <?php echo $depense['vehicule_id']==$v['id']?'selected':''; ?>><?php echo e($v['immatriculation'].' - '.$v['marque']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Nature</label><input type="text" name="nature" class="form-control" value="<?php echo e($depense['nature'] ?? ''); ?>"></div>
                    <div class="col-md-6"><label class="form-label">Nouveau justificatif</label><input type="file" name="justificatif" class="form-control" accept=".pdf,.jpg,.jpeg,.png"><?php if($depense['justificatif_path']): ?><small class="text-muted">Actuel : <?php echo e($depense['justificatif_path']); ?></small><?php endif; ?></div>
                    <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"><?php echo e($depense['description'] ?? ''); ?></textarea></div>
                    <div class="col-12"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button><a href="index.php" class="btn btn-outline-secondary ms-2">Annuler</a></div>
                </div>
            </form>
        </div></div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>