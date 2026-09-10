<?php
$pageTitle = 'Détail Dépense';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('depenses');

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT d.*, c.nom as cat_nom, a.nomagence as agence_nom, v.immatriculation, t.nom as type_op_nom, u.full_name as created_by_name, uv.full_name as validated_by_name FROM depenses d LEFT JOIN categories c ON d.categorie_id=c.id LEFT JOIN agence a ON d.agence_id=a.id LEFT JOIN vehicules v ON d.vehicule_id=v.id LEFT JOIN types_operations t ON d.type_operation_id=t.id LEFT JOIN users u ON d.created_by=u.id LEFT JOIN users uv ON d.validated_by=uv.id WHERE d.id=?");
$stmt->execute([$id]);
$d = $stmt->fetch();
if (!$d) redirectWithMessage(APP_URL . '/depenses/', 'error', 'Dépense introuvable.');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action==='validate' && hasPermission('depenses_validate')) {
        $db->prepare("UPDATE depenses SET statut='validee', validated_by=? WHERE id=? AND statut='en_attente'")->execute([$_SESSION['user_id'], $id]);
        addAuditLog('validate','depense',$id); redirectWithMessage(APP_URL.'/depenses/view.php?id='.$id,'success','Dépense validée.');
    } elseif ($action==='cancel' && hasPermission('depenses_validate')) {
        $db->prepare("UPDATE depenses SET statut='annulee', validated_by=? WHERE id=? AND statut='en_attente'")->execute([$_SESSION['user_id'], $id]);
        addAuditLog('cancel','depense',$id); redirectWithMessage(APP_URL.'/depenses/view.php?id='.$id,'warning','Dépense annulée.');
    }
}
?>
<div class="main-content" id="main-content" role="main">
    <header class="main-header" role="banner"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button><span class="mb-0 fw-bold"><?php echo e($pageTitle); ?></span></div><div class="header-right"><div class="dropdown"><button class="notif-btn" aria-label="Notifications" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" role="button" tabindex="0" aria-label="Menu utilisateur" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-cart-dash me-2"></i>Dépense - <?php echo e($d['reference']); ?></h1></div><a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>
        <div class="row g-3">
            <div class="col-lg-8"><div class="card"><div class="card-header"><i class="bi bi-info-circle me-2"></i>Informations</div><div class="card-body">
                <table class="table table-borderless">
                    <tr><th width="180">Référence</th><td><?php echo e($d['reference']); ?></td></tr>
                    <tr><th>Date</th><td><?php echo formatDateShort($d['date_depense']); ?></td></tr>
                    <tr><th>Montant</th><td class="fw-bold text-danger fs-5"><?php echo formatMoney($d['montant']); ?></td></tr>
                    <tr><th>Catégorie</th><td><?php echo e($d['cat_nom'] ?? '-'); ?></td></tr>
                    <tr><th>Type d'opération</th><td><?php echo e($d['type_op_nom'] ?? '-'); ?></td></tr>
                    <tr><th>Agence</th><td><?php echo e($d['agence_nom'] ?? '-'); ?></td></tr>
                    <tr><th>Camion</th><td><?php echo e($d['immatriculation'] ?? '-'); ?></td></tr>
                    <tr><th>Nature</th><td><?php echo e($d['nature'] ?? '-'); ?></td></tr>
                    <tr><th>Description</th><td><?php echo e($d['description'] ?? '-'); ?></td></tr>
                    <tr><th>Statut</th><td><?php echo getStatusBadge($d['statut']); ?></td></tr>
                    <tr><th>Créé par</th><td><?php echo e($d['created_by_name'] ?? '-'); ?> le <?php echo formatDate($d['created_at']); ?></td></tr>
                    <?php if($d['validated_by_name']): ?><tr><th>Validé par</th><td><?php echo e($d['validated_by_name']); ?></td></tr><?php endif; ?>
                    <?php if($d['justificatif_path']): ?><tr><th>Justificatif</th><td><a href="<?php echo APP_URL; ?>/uploads/justificatifs/<?php echo e($d['justificatif_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark me-1"></i>Voir</a></td></tr><?php endif; ?>
                </table>
            </div></div></div>
            <div class="col-lg-4"><div class="card"><div class="card-header"><i class="bi bi-gear me-2"></i>Actions</div><div class="card-body">
                <?php if($d['statut']==='en_attente'): ?>
                    <?php if(hasPermission('depenses_edit')): ?><a href="edit.php?id=<?php echo $d['id']; ?>" class="btn btn-warning w-100 mb-2"><i class="bi bi-pencil me-1"></i>Modifier</a><?php endif; ?>
                    <?php if(hasPermission('depenses_validate')): ?>
                    <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="validate"><button type="submit" class="btn btn-success w-100 mb-2" data-confirm="Valider cette dépense ?"><i class="bi bi-check-lg me-1"></i>Valider</button></form>
                    <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="cancel"><button type="submit" class="btn btn-danger w-100 mb-2" data-confirm="Annuler cette dépense ?"><i class="bi bi-x-lg me-1"></i>Annuler</button></form>
                    <?php endif; ?>
                <?php endif; ?>
                <button class="btn btn-outline-secondary w-100 no-print" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimer</button>
            </div></div></div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>