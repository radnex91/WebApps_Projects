<?php
$pageTitle = 'Dépenses';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('depenses');

$db = getDB();
$filterDateFrom = $_GET['date_from'] ?? date('Y-m-01');
$filterDateTo = $_GET['date_to'] ?? date('Y-m-t');
$filterAgence = $_GET['agence_id'] ?? '';
$filterCategorie = $_GET['categorie_id'] ?? '';
$filterStatut = $_GET['statut'] ?? '';
$filterSearch = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'validate' && hasPermission('depenses_validate')) {
        $db->prepare("UPDATE depenses SET statut='validee', validated_by=? WHERE id=? AND statut='en_attente'")->execute([$_SESSION['user_id'], $id]);
        addAuditLog('validate', 'depense', $id); setFlash('success', 'Dépense validée.');
    } elseif ($action === 'cancel' && hasPermission('depenses_validate')) {
        $db->prepare("UPDATE depenses SET statut='annulee', validated_by=? WHERE id=? AND statut='en_attente'")->execute([$_SESSION['user_id'], $id]);
        addAuditLog('cancel', 'depense', $id); setFlash('warning', 'Dépense annulée.');
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET)); exit;
}

$where = ["d.date_depense BETWEEN ? AND ?"];
$params = [$filterDateFrom, $filterDateTo];
if ($filterAgence) { $where[] = "d.agence_id = ?"; $params[] = $filterAgence; }
if ($filterCategorie) { $where[] = "d.categorie_id = ?"; $params[] = $filterCategorie; }
if ($filterStatut) { $where[] = "d.statut = ?"; $params[] = $filterStatut; }
if ($filterSearch) { $where[] = "(d.reference LIKE ? OR d.description LIKE ? OR d.nature LIKE ?)"; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; }

$whereClause = implode(' AND ', $where);
$query = "SELECT d.*, c.nom as cat_nom, a.nomagence as agence_nom, v.immatriculation FROM depenses d LEFT JOIN categories c ON d.categorie_id=c.id LEFT JOIN agence a ON d.agence_id=a.id LEFT JOIN vehicules v ON d.vehicule_id=v.id WHERE $whereClause ORDER BY d.date_depense DESC, d.created_at DESC";
$result = paginate($db, $query, $params, $page);
$agences = getAgences($db);
$categories = getCategories($db, 'depense');
$totalMontant = array_sum(array_column($result['data'], 'montant'));
?>
<div class="main-content">
    <header class="main-header"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button><h6 class="mb-0 fw-bold"><?php echo e($pageTitle); ?></h6></div><div class="header-right"><div class="dropdown"><button class="notif-btn" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-cart-dash me-2"></i>Dépenses</h1><p class="page-subtitle">Gestion des dépenses</p></div>
            <?php if (hasPermission('depenses_create')): ?><a href="create.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nouvelle dépense</a><?php endif; ?>
        </div>
        <div class="card mb-3"><div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2"><label class="form-label">Du</label><input type="date" name="date_from" value="<?php echo e($filterDateFrom); ?>" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Au</label><input type="date" name="date_to" value="<?php echo e($filterDateTo); ?>" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Agence</label><select name="agence_id" class="form-select"><option value="">Toutes</option><?php foreach($agences as $a): ?><option value="<?php echo $a['id']; ?>" <?php echo $filterAgence==$a['id']?'selected':''; ?>><?php echo e($a['nomagence']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Catégorie</label><select name="categorie_id" class="form-select"><option value="">Toutes</option><?php foreach($categories as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo $filterCategorie==$c['id']?'selected':''; ?>><?php echo e($c['nom']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Statut</label><select name="statut" class="form-select"><option value="">Tous</option><?php foreach(STATUSES as $k=>$l): ?><option value="<?php echo $k; ?>" <?php echo $filterStatut===$k?'selected':''; ?>><?php echo e($l); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search me-1"></i>Filtrer</button></div>
            </form>
        </div></div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted">Total : <strong class="text-danger"><?php echo formatMoney($totalMontant); ?></strong></span>
            <span class="text-muted"><?php echo $result['total']; ?> résultat(s)</span>
        </div>
        <div class="card"><div class="table-container">
            <table class="table">
                <thead><tr><th>Référence</th><th>Date</th><th>Montant</th><th>Catégorie</th><th>Agence</th><th>Nature</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($result['data'] as $d): ?>
                <tr>
                    <td><small class="fw-600"><?php echo e($d['reference']); ?></small></td>
                    <td><?php echo formatDateShort($d['date_depense']); ?></td>
                    <td class="fw-bold text-danger"><?php echo formatMoney($d['montant']); ?></td>
                    <td><small><?php echo e($d['cat_nom'] ?? '-'); ?></small></td>
                    <td><small><?php echo e($d['agence_nom'] ?? '-'); ?></small></td>
                    <td><small><?php echo e($d['nature'] ?? '-'); ?></small></td>
                    <td><?php echo getStatusBadge($d['statut']); ?></td>
                    <td><div class="action-btns">
                        <a href="view.php?id=<?php echo $d['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        <?php if ($d['statut']==='en_attente' && hasPermission('depenses_edit')): ?>
                        <a href="edit.php?id=<?php echo $d['id']; ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                        <?php if ($d['statut']==='en_attente' && hasPermission('depenses_validate')): ?>
                        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="id" value="<?php echo $d['id']; ?>"><input type="hidden" name="action" value="validate"><button type="submit" class="btn btn-sm btn-outline-success" data-confirm="Valider ?"><i class="bi bi-check-lg"></i></button></form>
                        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="id" value="<?php echo $d['id']; ?>"><input type="hidden" name="action" value="cancel"><button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Annuler ?"><i class="bi bi-x-lg"></i></button></form>
                        <?php endif; ?>
                    </div></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($result['data'])): ?><tr><td colspan="8" class="text-center text-muted py-4">Aucune dépense trouvée</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
        <?php echo renderPagination($result['page'], $result['totalPages'], '?' . http_build_query(array_filter(['date_from'=>$filterDateFrom,'date_to'=>$filterDateTo,'agence_id'=>$filterAgence,'categorie_id'=>$filterCategorie,'statut'=>$filterStatut]))); ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>