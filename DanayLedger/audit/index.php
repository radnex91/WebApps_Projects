<?php
$pageTitle = 'Journal d\'Audit';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('audit');

$db = getDB();
$page = max(1, (int)($_GET['page'] ?? 1));
$filterAction = $_GET['action'] ?? '';
$filterEntity = $_GET['entity'] ?? '';
$filterUser = (int)($_GET['user_id'] ?? 0) ?: null;
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

$where = ["1=1"];
$params = [];
if ($filterAction) { $where[] = "a.action = ?"; $params[] = $filterAction; }
if ($filterEntity) { $where[] = "a.entity_type = ?"; $params[] = $filterEntity; }
if ($filterUser) { $where[] = "a.user_id = ?"; $params[] = $filterUser; }
if ($filterDateFrom) { $where[] = "a.created_at >= ?"; $params[] = $filterDateFrom . ' 00:00:00'; }
if ($filterDateTo) { $where[] = "a.created_at <= ?"; $params[] = $filterDateTo . ' 23:59:59'; }

$whereClause = implode(' AND ', $where);
$query = "SELECT a.*, u.username, u.full_name FROM audit_logs a LEFT JOIN users u ON a.user_id=u.id WHERE $whereClause ORDER BY a.created_at DESC";
$result = paginate($db, $query, $params, $page);

$users = $db->query("SELECT id, username, full_name FROM users ORDER BY full_name")->fetchAll();
$actionLabels = ['create'=>'Création','update'=>'Modification','delete'=>'Suppression','validate'=>'Validation','cancel'=>'Annulation','login'=>'Connexion','logout'=>'Déconnexion','export'=>'Export','import'=>'Import','backup'=>'Sauvegarde','restore'=>'Restauration'];
$entityLabels = ['user'=>'Utilisateur','recette'=>'Recette','depense'=>'Dépense','recette_camion'=>'Recette camion','versement'=>'Versement','rapprochement'=>'Rapprochement','agence'=>'Agence','categorie'=>'Catégorie','vehicule'=>'Véhicule','parametre'=>'Paramètre'];
?>
<div class="main-content">
    <header class="main-header"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button><h6 class="mb-0 fw-bold"><?php echo e($pageTitle); ?></h6></div><div class="header-right"><div class="dropdown"><button class="notif-btn" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-journal-text me-2"></i>Journal d'Audit</h1><p class="page-subtitle">Historique de toutes les actions</p></div></div>

        <div class="card mb-3"><div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2"><label class="form-label">Action</label><select name="action" class="form-select"><option value="">Toutes</option><?php foreach($actionLabels as $k=>$l): ?><option value="<?php echo $k; ?>" <?php echo $filterAction===$k?'selected':''; ?>><?php echo e($l); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Entité</label><select name="entity" class="form-select"><option value="">Toutes</option><?php foreach($entityLabels as $k=>$l): ?><option value="<?php echo $k; ?>" <?php echo $filterEntity===$k?'selected':''; ?>><?php echo e($l); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Utilisateur</label><select name="user_id" class="form-select"><option value="">Tous</option><?php foreach($users as $u): ?><option value="<?php echo $u['id']; ?>" <?php echo $filterUser==$u['id']?'selected':''; ?>><?php echo e($u['full_name']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Du</label><input type="date" name="date_from" value="<?php echo e($filterDateFrom); ?>" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Au</label><input type="date" name="date_to" value="<?php echo e($filterDateTo); ?>" class="form-control"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Filtrer</button></div>
            </form>
        </div></div>

        <div class="card"><div class="table-container">
            <table class="table">
                <thead><tr><th>Date/Heure</th><th>Utilisateur</th><th>Action</th><th>Entité</th><th>ID</th><th>Détails</th><th>IP</th></tr></thead>
                <tbody>
                <?php foreach ($result['data'] as $log): ?>
                <tr>
                    <td><small><?php echo formatDate($log['created_at']); ?></small></td>
                    <td><?php echo e($log['full_name'] ?? $log['username'] ?? 'Système'); ?></td>
                    <td><span class="badge <?php echo match($log['action']){'create'=>'bg-success','update'=>'bg-warning text-dark','delete'=>'bg-danger','validate'=>'bg-info','cancel'=>'bg-secondary','login'=>'bg-primary','logout'=>'bg-secondary',default=>'bg-secondary'}; ?>"><?php echo e($actionLabels[$log['action']] ?? $log['action']); ?></span></td>
                    <td><small><?php echo e($entityLabels[$log['entity_type']] ?? $log['entity_type']); ?></small></td>
                    <td><?php echo $log['entity_id'] ?? '-'; ?></td>
                    <td><small><?php
                        $details = json_decode($log['details'] ?? '{}', true);
                        if ($log['old_values'] || $log['new_values']) {
                            $old = json_decode($log['old_values'] ?? '{}', true);
                            $new = json_decode($log['new_values'] ?? '{}', true);
                            echo 'Avant: ' . implode(', ', array_map(fn($k,$v)=>"$k=$v", array_keys($old?:[]), $old?:[])) . ' → Après: ' . implode(', ', array_map(fn($k,$v)=>"$k=$v", array_keys($new?:[]), $new?:[]));
                        } else {
                            echo implode(', ', array_map(fn($k,$v)=>"$k=$v", array_keys($details?:[]), $details?:[]));
                        }
                    ?></small></td>
                    <td><small class="text-muted"><?php echo e($log['ip_address'] ?? ''); ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($result['data'])): ?><tr><td colspan="7" class="text-center text-muted py-4">Aucune entrée</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
        <?php echo renderPagination($result['page'], $result['totalPages'], '?' . http_build_query(array_filter(['action'=>$filterAction,'entity'=>$filterEntity,'user_id'=>$filterUser,'date_from'=>$filterDateFrom,'date_to'=>$filterDateTo]))); ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>