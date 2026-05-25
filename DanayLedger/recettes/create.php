<?php
$pageTitle = 'Nouvelle Recette';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('recettes_create');

$db = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { $error = 'Token de sécurité invalide.'; }
    else {
        $date = $_POST['date'] ?? '';
        $agence_id = (int)($_POST['agence_id'] ?? 0) ?: null;
        $montantAccompagnement = (float)($_POST['montantAccompagnement'] ?? 0);
        $montantexpedition = (float)($_POST['montantexpedition'] ?? 0);
        $libelle = cleanInput($_POST['libelle'] ?? '');
        $nomoperateur = $_SESSION['full_name'] ?? '';

        if (empty($date) || !$agence_id) { $error = 'La date et l\'agence sont obligatoires.'; }
        elseif (($montantexpedition + $montantAccompagnement) <= 0) { $error = 'Au moins un montant est obligatoire.'; }
        else {
            $reference = generateReference('RJR');
            try {
                $stmt = $db->prepare("INSERT INTO recette (reference, date, montantexpedition, montantAccompagnement, agence_id, nomoperateur, description, created_by) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$reference, $date, $montantexpedition, $montantAccompagnement, $agence_id, $nomoperateur, $libelle, $_SESSION['user_id']]);
                $newId = $db->lastInsertId();
                if (isset($_FILES['justificatif']) && $_FILES['justificatif']['error'] === UPLOAD_ERR_OK) {
                    $path = uploadJustificatif($_FILES['justificatif'], 'recette', $newId);
                    if ($path) $db->prepare("UPDATE recette SET justificatif_path=? WHERE id=?")->execute([$path, $newId]);
                }
                addAuditLog('create', 'recette', $newId, [], ['reference'=>$reference,'montantexpedition'=>$montantexpedition,'montantAccompagnement'=>$montantAccompagnement]);
                redirectWithMessage(APP_URL . '/recettes/', 'success', 'Recette créée avec succès.');
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
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-cash-coin me-2"></i>Nouvelle Recette Journalière</h1></div><a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>
        <div class="card"><div class="card-body">
            <form method="POST" enctype="multipart/form-data" data-validate>
                <?php echo csrfField(); ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Date recette <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control form-control-lg" value="<?php echo e($_POST['date'] ?? date('Y-m-d')); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Agence <span class="text-danger">*</span></label>
                        <select name="agence_id" class="form-select form-select-lg" required>
                            <option value="">-- Sélectionner l'agence --</option>
                            <?php foreach($agences as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo (($_POST['agence_id']??'')==$a['id']?'selected':''); ?>><?php echo e($a['nomagence']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Montant accompagnés (FCFA)</label>
                        <input type="number" name="montantAccompagnement" class="form-control form-control-lg" min="0" step="1" value="<?php echo e($_POST['montantAccompagnement'] ?? '0'); ?>" placeholder="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Montant expédition (FCFA) <span class="text-danger">*</span></label>
                        <input type="number" name="montantexpedition" class="form-control form-control-lg" min="0" step="1" value="<?php echo e($_POST['montantexpedition'] ?? ''); ?>" placeholder="0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Opérateur de saisie</label>
                        <input type="text" class="form-control form-control-lg" value="<?php echo e($_SESSION['full_name'] ?? ''); ?>" readonly style="background-color:#e9ecef;">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Libellé</label>
                        <input type="text" name="libelle" class="form-control" value="<?php echo e($_POST['libelle'] ?? ''); ?>" placeholder="Description de la recette...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Justificatif</label>
                        <input type="file" name="justificatif" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
                        <a href="index.php" class="btn btn-outline-secondary btn-lg ms-2">Annuler</a>
                    </div>
                </div>
            </form>
        </div></div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>