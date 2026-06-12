<?php
$pageTitle = 'Nouvelle DÃ©pense';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('depenses_create');

$db = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { $error = 'Token invalide.'; }
    else {
        $date_depense = $_POST['date_depense'] ?? '';
        $montant = (float)str_replace([' ', "\u{00A0}", "\u{202F}"], '', $_POST['montant'] ?? '0');
        $categorie_id = (int)($_POST['categorie_id'] ?? 0) ?: null;
        $type_operation_id = (int)($_POST['type_operation_id'] ?? 0) ?: null;
        $agence_id = (int)($_POST['agence_id'] ?? 0) ?: null;
        $vehicule_id = (int)($_POST['vehicule_id'] ?? 0) ?: null;
        $nature = cleanInput($_POST['nature'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');

        if (empty($date_depense) || $montant <= 0) { $error = 'La date et le montant sont obligatoires.'; }
        else {
            $reference = generateReference('DEP');
            try {
                $stmt = $db->prepare("INSERT INTO depenses (reference, date_depense, montant, categorie_id, type_operation_id, agence_id, vehicule_id, nature, description, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$reference, $date_depense, $montant, $categorie_id, $type_operation_id, $agence_id, $vehicule_id, $nature, $description, $_SESSION['user_id']]);
                $newId = $db->lastInsertId();
                if (isset($_FILES['justificatif']) && $_FILES['justificatif']['error'] === UPLOAD_ERR_OK) {
                    $path = uploadJustificatif($_FILES['justificatif'], 'depense', $newId);
                    if ($path) $db->prepare("UPDATE depenses SET justificatif_path=? WHERE id=?")->execute([$path, $newId]);
                }
                addAuditLog('create', 'depense', $newId, [], ['reference'=>$reference,'montant'=>$montant]);
                redirectWithMessage(APP_URL . '/depenses/', 'success', 'DÃ©pense crÃ©Ã©e avec succÃ¨s.');
            } catch (Exception $e) { $error = 'Erreur : ' . $e->getMessage(); }
        }
    }
}
$agences = getAgences($db); $categories = getCategories($db, 'depense'); $vehicules = getVehicules($db);
$typesOps = $db->query("SELECT id, code, nom FROM types_operations WHERE statut='actif' ORDER BY nom")->fetchAll();
?>
<div class="main-content" id="main-content" role="main">
    <header class="main-header" role="banner"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button><span class="mb-0 fw-bold"><?php echo e($pageTitle); ?></span></div><div class="header-right"><div class="dropdown"><button class="notif-btn" aria-label="Notifications" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" role="button" tabindex="0" aria-label="Menu utilisateur" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>DÃ©connexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?php echo e($error); ?></div><?php endif; ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-cart-dash me-2"></i>Nouvelle DÃ©pense</h1></div><a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>
        <div class="card"><div class="card-body">
            <form method="POST" enctype="multipart/form-data" data-validate>
                <?php echo csrfField(); ?>
                <div class="row g-3">
                    <div class="col-md-6"><label for="date_depense" class="form-label">Date <span class="text-danger">*</span></label><input type="date" name="date_depense" id="date_depense" class="form-control" value="<?php echo e($_POST['date_depense'] ?? date('Y-m-d')); ?>" required></div>
                    <div class="col-md-6"><label for="montant" class="form-label">Montant (FCFA) <span class="text-danger">*</span></label><input type="text" inputmode="numeric" class="form-control amount-input" name="montant" id="montant" min="0" step="1" value="<?php echo e($_POST['montant'] ?? ''); ?>" required></div>
                    <div class="col-md-6"><label for="categorie_id" class="form-label">CatÃ©gorie</label><select name="categorie_id" id="categorie_id" class="form-select"><option value="">-- SÃ©lectionner --</option><?php foreach($categories as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo (($_POST['categorie_id']??'')==$c['id']?'selected':''); ?>><?php echo e($c['nom']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label for="type_operation_id" class="form-label">Type d'opÃ©ration</label><select name="type_operation_id" id="type_operation_id" class="form-select"><option value="">-- SÃ©lectionner --</option><?php foreach($typesOps as $t): ?><option value="<?php echo $t['id']; ?>" <?php echo (($_POST['type_operation_id']??'')==$t['id']?'selected':''); ?>><?php echo e($t['nom']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label for="agence_id" class="form-label">Agence</label><select name="agence_id" id="agence_id" class="form-select"><option value="">-- SÃ©lectionner --</option><?php foreach($agences as $a): ?><option value="<?php echo $a['id']; ?>" <?php echo (($_POST['agence_id']??'')==$a['id']?'selected':''); ?>><?php echo e($a['nomagence']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label for="vehicule_id" class="form-label">Camion</label><select name="vehicule_id" id="vehicule_id" class="form-select"><option value="">-- Aucun --</option><?php foreach($vehicules as $v): ?><option value="<?php echo $v['id']; ?>" <?php echo (($_POST['vehicule_id']??'')==$v['id']?'selected':''); ?>><?php echo e($v['immatriculation'].' - '.$v['marque']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label for="nature" class="form-label">Nature</label><input type="text" name="nature" id="nature" class="form-control" value="<?php echo e($_POST['nature'] ?? ''); ?>" placeholder="Ex: Carburant, Maintenance..."></div>
                    <div class="col-md-6"><label for="justificatif" class="form-label">Justificatif</label><input type="file" name="justificatif" id="justificatif" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
                    <div class="col-12"><label for="description" class="form-label">Description</label><textarea name="description" id="description" class="form-control" rows="3"><?php echo e($_POST['description'] ?? ''); ?></textarea></div>
                    <div class="col-12"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button><a href="index.php" class="btn btn-outline-secondary ms-2">Annuler</a></div>
                </div>
            </form>
        </div></div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
