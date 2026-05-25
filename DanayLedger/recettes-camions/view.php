<?php
$pageTitle = 'Détail Recette Camion';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('recettes_camions');

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT rc.*, v.immatriculation, v.marque as v_marque, ad.nomagence as agence_depart_nom, aa.nomagence as agence_arrivee_nom, c.nom as cat_nom, u.full_name as created_by_name, uv.full_name as validated_by_name FROM recettes_camions rc LEFT JOIN vehicules v ON rc.vehicule_id=v.id LEFT JOIN agence ad ON rc.agence_depart_id=ad.id LEFT JOIN agence aa ON rc.agence_arrivee_id=aa.id LEFT JOIN categories c ON rc.categorie_id=c.id LEFT JOIN users u ON rc.created_by=u.id LEFT JOIN users uv ON rc.validated_by=uv.id WHERE rc.id = ?");
$stmt->execute([$id]);
$recette = $stmt->fetch();

if (!$recette) redirectWithMessage(APP_URL . '/recettes-camions/', 'error', 'Recette camion introuvable.');

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'validate' && hasPermission('recettes_camions_validate')) {
        $db->prepare("UPDATE recettes_camions SET statut='validee', validated_by=? WHERE id=? AND statut='en_attente'")->execute([$_SESSION['user_id'], $id]);
        addAuditLog('validate', 'recette_camion', $id);
        redirectWithMessage(APP_URL . '/recettes-camions/view.php?id=' . $id, 'success', 'Recette camion validée.');
    } elseif ($action === 'cancel' && hasPermission('recettes_camions_validate')) {
        $db->prepare("UPDATE recettes_camions SET statut='annulee', validated_by=? WHERE id=? AND statut='en_attente'")->execute([$_SESSION['user_id'], $id]);
        addAuditLog('cancel', 'recette_camion', $id);
        redirectWithMessage(APP_URL . '/recettes-camions/view.php?id=' . $id, 'warning', 'Recette camion annulée.');
    }
}
?>

<div class="main-content">
    <header class="main-header">
        <div class="header-left"><button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button><h6 class="mb-0 fw-bold"><?php echo e($pageTitle); ?></h6></div>
        <div class="header-right">
            <div class="dropdown"><button class="notif-btn" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div>
            <div class="dropdown"><div class="header-user" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div>
        </div>
    </header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header">
            <div><h1 class="page-title"><i class="bi bi-truck me-2"></i>Recette Camion - <?php echo e($recette['reference']); ?></h1></div>
            <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><i class="bi bi-info-circle me-2"></i>Informations</div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr><th width="180">Référence</th><td><?php echo e($recette['reference']); ?></td></tr>
                            <tr><th>Date</th><td><?php echo formatDateShort($recette['date_recette']); ?></td></tr>
                            <tr><th>Montant</th><td class="fw-bold text-success fs-5"><?php echo formatMoney($recette['montant']); ?></td></tr>
                            <tr><th>Camion</th><td><?php echo e(($recette['immatriculation'] ?? '-') . ' - ' . ($recette['v_marque'] ?? '')); ?></td></tr>
                            <tr><th>Agence de départ</th><td><?php echo e($recette['agence_depart_nom'] ?? '-'); ?></td></tr>
                            <tr><th>Agence d'arrivée</th><td><?php echo e($recette['agence_arrivee_nom'] ?? '-'); ?></td></tr>
                            <tr><th>Catégorie</th><td><?php echo e($recette['cat_nom'] ?? '-'); ?></td></tr>
                            <?php if (!empty($recette['client'])): ?><tr><th>Client</th><td><?php echo e($recette['client']); ?></td></tr><?php endif; ?>
                            <tr><th>Observation</th><td><?php echo e($recette['description'] ?? '-'); ?></td></tr>
                            <tr><th>Statut</th><td><?php echo getStatusBadge($recette['statut']); ?></td></tr>
                            <tr><th>Créé par</th><td><?php echo e($recette['created_by_name'] ?? '-'); ?> le <?php echo formatDate($recette['created_at']); ?></td></tr>
                            <?php if ($recette['validated_by_name']): ?><tr><th>Validé par</th><td><?php echo e($recette['validated_by_name']); ?></td></tr><?php endif; ?>
                            <?php if ($recette['justificatif_path']): ?><tr><th>Justificatif</th><td><a href="<?php echo APP_URL; ?>/uploads/justificatifs/<?php echo e($recette['justificatif_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark me-1"></i>Voir le justificatif</a></td></tr><?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><i class="bi bi-gear me-2"></i>Actions</div>
                    <div class="card-body">
                        <?php if ($recette['statut'] === 'en_attente'): ?>
                            <?php if (hasPermission('recettes_camions_edit')): ?>
                            <a href="index.php" class="btn btn-warning w-100 mb-2"><i class="bi bi-pencil me-1"></i>Modifier</a>
                            <?php endif; ?>
                            <?php if (hasPermission('recettes_camions_validate')): ?>
                            <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="validate"><button type="submit" class="btn btn-success w-100 mb-2" data-confirm="Valider cette recette ?"><i class="bi bi-check-lg me-1"></i>Valider</button></form>
                            <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="cancel"><button type="submit" class="btn btn-danger w-100 mb-2" data-confirm="Annuler cette recette ?"><i class="bi bi-x-lg me-1"></i>Annuler</button></form>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button class="btn btn-outline-secondary w-100 no-print" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimer</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>