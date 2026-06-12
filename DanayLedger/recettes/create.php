<?php
$pageTitle = 'Nouvelle Recette';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('recettes_create');

$db = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { $error = 'Token de sÃ©curitÃ© invalide.'; }
    else {
        $date = $_POST['date'] ?? '';
        $agence_id = (int)($_POST['agence_id'] ?? 0) ?: null;
        $montantAccompagnement = (float)str_replace([' ', "\u{00A0}", "\u{202F}"], '', $_POST['montantAccompagnement'] ?? '0');
        $montantexpedition = (float)str_replace([' ', "\u{00A0}", "\u{202F}"], '', $_POST['montantexpedition'] ?? '0');
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
                redirectWithMessage(APP_URL . '/recettes/', 'success', 'Recette crÃ©Ã©e avec succÃ¨s.');
            } catch (Exception $e) { $error = 'Erreur : ' . $e->getMessage(); }
        }
    }
}
$agences = getAgences($db);
?>
<div class="main-content" id="main-content" role="main">
    <header class="main-header" role="banner"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button><span class="mb-0 fw-bold"><?php echo e($pageTitle); ?></span></div><div class="header-right"><div class="dropdown"><button class="notif-btn" aria-label="Notifications" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" role="button" tabindex="0" aria-label="Menu utilisateur" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>DÃ©connexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?php echo e($error); ?></div><?php endif; ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-cash-coin me-2"></i>Nouvelle Recette JournaliÃ¨re</h1></div><a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>
        <div class="card"><div class="card-body">
            <form method="POST" enctype="multipart/form-data" data-validate>
                <?php echo csrfField(); ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="date" class="form-label fw-semibold">Date recette <span class="text-danger">*</span></label>
                        <input type="date" name="date" id="date" class="form-control form-control-lg" value="<?php echo e($_POST['date'] ?? date('Y-m-d')); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="agence_id" class="form-label fw-semibold">Agence <span class="text-danger">*</span></label>
                        <select name="agence_id" id="agence_id" class="form-select form-select-lg" required>
                            <option value="">-- SÃ©lectionner l'agence --</option>
                            <?php foreach($agences as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo (($_POST['agence_id']??'')==$a['id']?'selected':''); ?>><?php echo e($a['nomagence']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="montantAccompagnement" class="form-label fw-semibold">Montant accompagnÃ©s (FCFA)</label>
                        <input type="text" inputmode="numeric" class="form-control form-control-lg amount-input" name="montantAccompagnement" id="montantAccompagnement" min="0" step="1" value="<?php echo e($_POST['montantAccompagnement'] ?? '0'); ?>" placeholder="0">
                    </div>
                    <div class="col-md-6">
                        <label for="montantexpedition" class="form-label fw-semibold">Montant expÃ©dition (FCFA) <span class="text-danger">*</span></label>
                        <input type="text" inputmode="numeric" class="form-control form-control-lg amount-input" name="montantexpedition" id="montantexpedition" min="0" step="1" value="<?php echo e($_POST['montantexpedition'] ?? ''); ?>" placeholder="0" required>
                    </div>
                    <div class="col-md-6">
                        <label for="nomoperateur" class="form-label fw-semibold">OpÃ©rateur de saisie</label>
                        <input type="text" class="form-control form-control-lg" id="nomoperateur" value="<?php echo e($_SESSION['full_name'] ?? ''); ?>" readonly style="background-color:var(--surface-alt);">
                    </div>
                    <div class="col-12">
                        <label for="libelle" class="form-label fw-semibold">LibellÃ©</label>
                        <input type="text" name="libelle" id="libelle" class="form-control" value="<?php echo e($_POST['libelle'] ?? ''); ?>" placeholder="Description de la recette...">
                    </div>
                    <div class="col-md-6">
                        <label for="justificatif" class="form-label">Justificatif</label>
                        <input type="file" name="justificatif" id="justificatif" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
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
