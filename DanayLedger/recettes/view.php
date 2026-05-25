<?php
$pageTitle = 'Détail Recette';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('recettes');

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT r.*, a.nomagence, u.full_name as created_by_name, uv.full_name as validated_by_name FROM recette r LEFT JOIN agence a ON r.agence_id=a.id LEFT JOIN users u ON r.created_by=u.id LEFT JOIN users uv ON r.validated_by=uv.id WHERE r.id=?");
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) redirectWithMessage(APP_URL . '/recettes/', 'error', 'Recette introuvable.');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action==='validate' && hasPermission('recettes_validate')) {
        $db->prepare("UPDATE recette SET statut='validee', validated_by=? WHERE id=? AND statut='en_attente'")->execute([$_SESSION['user_id'], $id]);
        addAuditLog('validate','recette',$id); redirectWithMessage(APP_URL.'/recettes/view.php?id='.$id,'success','Recette validée.');
    } elseif ($action==='cancel' && hasPermission('recettes_validate')) {
        $db->prepare("UPDATE recette SET statut='annulee', validated_by=? WHERE id=? AND statut='en_attente'")->execute([$_SESSION['user_id'], $id]);
        addAuditLog('cancel','recette',$id); redirectWithMessage(APP_URL.'/recettes/view.php?id='.$id,'warning','Recette annulée.');
    }
}
?>
<div class="main-content">
    <header class="main-header"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button><h6 class="mb-0 fw-bold"><?php echo e($pageTitle); ?></h6></div><div class="header-right"><div class="dropdown"><button class="notif-btn" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-cash-coin me-2"></i>Recette - <?php echo e($r['reference']); ?></h1></div><a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>
        <div class="row g-3">
            <div class="col-lg-8"><div class="card"><div class="card-header"><i class="bi bi-info-circle me-2"></i>Informations</div><div class="card-body">
                <table class="table table-borderless">
                    <tr><th width="180">Référence</th><td><?php echo e($r['reference']); ?></td></tr>
                    <tr><th>Date</th><td><?php echo formatDateShort($r['date']); ?></td></tr>
                    <tr><th>Montant Expédition</th><td class="fw-bold text-success fs-5"><?php echo formatMoney($r['montantexpedition']); ?></td></tr>
                    <tr><th>Montant Accompagnement</th><td class="fw-bold text-info fs-5"><?php echo formatMoney($r['montantAccompagnement']); ?></td></tr>
                    <tr><th>Total</th><td class="fw-bold fs-4"><?php echo formatMoney($r['montantexpedition'] + $r['montantAccompagnement']); ?></td></tr>
                    <tr><th>Agence</th><td><?php echo e($r['nomagence'] ?? '-'); ?></td></tr>
                    <tr><th>Numéro de reçu</th><td><?php echo e($r['numerorecu'] ?? '-'); ?></td></tr>
                    <tr><th>Opérateur</th><td><?php echo e($r['nomoperateur'] ?? '-'); ?></td></tr>
                    <tr><th>Éditeur</th><td><?php echo e($r['nomediteur'] ?? '-'); ?></td></tr>
                    <tr><th>Date édition</th><td><?php echo formatDateShort($r['dateedite'] ?? ''); ?></td></tr>
                    <tr><th>Destination</th><td><?php echo e($r['destination'] ?? '-'); ?></td></tr>
                    <tr><th>Guichetier</th><td><?php echo e($r['guichetier'] ?? '-'); ?></td></tr>
                    <tr><th>Description</th><td><?php echo e($r['description'] ?? '-'); ?></td></tr>
                    <tr><th>Statut</th><td><?php echo getStatusBadge($r['statut']); ?></td></tr>
                    <tr><th>Créé par</th><td><?php echo e($r['created_by_name'] ?? '-'); ?> le <?php echo formatDate($r['created_at']); ?></td></tr>
                    <?php if($r['validated_by_name']): ?><tr><th>Validé par</th><td><?php echo e($r['validated_by_name']); ?></td></tr><?php endif; ?>
                    <?php if($r['justificatif_path']): ?><tr><th>Justificatif</th><td><a href="<?php echo APP_URL; ?>/uploads/justificatifs/<?php echo e($r['justificatif_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark me-1"></i>Voir</a></td></tr><?php endif; ?>
                </table>
            </div></div></div>
            <div class="col-lg-4"><div class="card"><div class="card-header"><i class="bi bi-gear me-2"></i>Actions</div><div class="card-body">
                <?php if($r['statut']==='en_attente'): ?>
                    <?php if(hasPermission('recettes_edit')): ?><a href="edit.php?id=<?php echo $r['id']; ?>" class="btn btn-warning w-100 mb-2"><i class="bi bi-pencil me-1"></i>Modifier</a><?php endif; ?>
                    <?php if(hasPermission('recettes_validate')): ?>
                    <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="validate"><button type="submit" class="btn btn-success w-100 mb-2" data-confirm="Valider cette recette ?"><i class="bi bi-check-lg me-1"></i>Valider</button></form>
                    <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="cancel"><button type="submit" class="btn btn-danger w-100 mb-2" data-confirm="Annuler cette recette ?"><i class="bi bi-x-lg me-1"></i>Annuler</button></form>
                    <?php endif; ?>
                <?php endif; ?>
                <button class="btn btn-outline-secondary w-100 no-print" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimer</button>
            </div></div></div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>