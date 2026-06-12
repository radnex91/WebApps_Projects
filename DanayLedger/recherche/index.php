<?php
$pageTitle = 'Recherche MulticritÃ¨re';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('recherche');

$db = getDB();
$results = [];
$searchPerformed = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
    $searchPerformed = true;
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    $agenceId = (int)($_GET['agence_id'] ?? 0) ?: null;
    $type = $_GET['type'] ?? '';
    $montantMin = (float)str_replace([' ', "\u{00A0}", "\u{202F}"], '', $_GET['montant_min'] ?? '0') ?: null;
    $montantMax = (float)str_replace([' ', "\u{00A0}", "\u{202F}"], '', $_GET['montant_max'] ?? '0') ?: null;
    $vehiculeId = (int)($_GET['vehicule_id'] ?? 0) ?: null;
    $statut = $_GET['statut'] ?? '';
    $keyword = cleanInput($_GET['keyword'] ?? '');
    $searchType = $_GET['search_type'] ?? 'all';

    // Recettes journaliÃ¨res
    if ($searchType === 'all' || $searchType === 'recettes') {
        $q = "SELECT 'recette' as source, r.id, r.reference, r.date as date_op, (r.montantexpedition + r.montantAccompagnement) as montant, r.statut, r.description, a.nomagence as agence_nom FROM recette r LEFT JOIN agence a ON r.agence_id=a.id WHERE 1=1";
        $p = [];
        if ($dateFrom) { $q .= " AND r.date >= ?"; $p[] = $dateFrom; }
        if ($dateTo) { $q .= " AND r.date <= ?"; $p[] = $dateTo; }
        if ($agenceId) { $q .= " AND r.agence_id = ?"; $p[] = $agenceId; }
        if ($montantMin) { $q .= " AND (r.montantexpedition + r.montantAccompagnement) >= ?"; $p[] = $montantMin; }
        if ($montantMax) { $q .= " AND (r.montantexpedition + r.montantAccompagnement) <= ?"; $p[] = $montantMax; }
        if ($statut) { $q .= " AND r.statut = ?"; $p[] = $statut; }
        if ($keyword) { $q .= " AND (r.reference LIKE ? OR r.description LIKE ?)"; $p[] = "%$keyword%"; $p[] = "%$keyword%"; }
        $q .= " ORDER BY r.date DESC LIMIT 50";
        $stmt = $db->prepare($q); $stmt->execute($p);
        $results = array_merge($results, $stmt->fetchAll());
    }

    // DÃ©penses
    if ($searchType === 'all' || $searchType === 'depenses') {
        $q = "SELECT 'depense' as source, d.id, d.reference, d.date_depense as date_op, d.montant, d.statut, d.description, a.nomagence as agence_nom, c.nom as cat_nom, v.immatriculation FROM depenses d LEFT JOIN agence a ON d.agence_id=a.id LEFT JOIN categories c ON d.categorie_id=c.id LEFT JOIN vehicules v ON d.vehicule_id=v.id WHERE 1=1";
        $p = [];
        if ($dateFrom) { $q .= " AND d.date_depense >= ?"; $p[] = $dateFrom; }
        if ($dateTo) { $q .= " AND d.date_depense <= ?"; $p[] = $dateTo; }
        if ($agenceId) { $q .= " AND d.agence_id = ?"; $p[] = $agenceId; }
        if ($montantMin) { $q .= " AND d.montant >= ?"; $p[] = $montantMin; }
        if ($montantMax) { $q .= " AND d.montant <= ?"; $p[] = $montantMax; }
        if ($vehiculeId) { $q .= " AND d.vehicule_id = ?"; $p[] = $vehiculeId; }
        if ($statut) { $q .= " AND d.statut = ?"; $p[] = $statut; }
        if ($keyword) { $q .= " AND (d.reference LIKE ? OR d.description LIKE ? OR d.nature LIKE ?)"; $p[] = "%$keyword%"; $p[] = "%$keyword%"; $p[] = "%$keyword%"; }
        $q .= " ORDER BY d.date_depense DESC LIMIT 50";
        $stmt = $db->prepare($q); $stmt->execute($p);
        $results = array_merge($results, $stmt->fetchAll());
    }

    // Recettes camions
    if ($searchType === 'all' || $searchType === 'recettes_camions') {
        $q = "SELECT 'recette_camion' as source, rc.id, rc.reference, rc.date_recette as date_op, rc.montant, rc.statut, rc.description, ad.nomagence as agence_depart_nom, aa.nomagence as agence_arrivee_nom, c.nom as cat_nom, v.immatriculation FROM recettes_camions rc LEFT JOIN agence ad ON rc.agence_depart_id=ad.id LEFT JOIN agence aa ON rc.agence_arrivee_id=aa.id LEFT JOIN categories c ON rc.categorie_id=c.id LEFT JOIN vehicules v ON rc.vehicule_id=v.id WHERE 1=1";
        $p = [];
        if ($dateFrom) { $q .= " AND rc.date_recette >= ?"; $p[] = $dateFrom; }
        if ($dateTo) { $q .= " AND rc.date_recette <= ?"; $p[] = $dateTo; }
        if ($agenceId) { $q .= " AND (rc.agence_depart_id = ? OR rc.agence_arrivee_id = ?)"; $p[] = $agenceId; $p[] = $agenceId; }
        if ($montantMin) { $q .= " AND rc.montant >= ?"; $p[] = $montantMin; }
        if ($montantMax) { $q .= " AND rc.montant <= ?"; $p[] = $montantMax; }
        if ($vehiculeId) { $q .= " AND rc.vehicule_id = ?"; $p[] = $vehiculeId; }
        if ($statut) { $q .= " AND rc.statut = ?"; $p[] = $statut; }
        if ($keyword) { $q .= " AND (rc.reference LIKE ? OR rc.description LIKE ? OR rc.client LIKE ? OR rc.trajet LIKE ?)"; $p[] = "%$keyword%"; $p[] = "%$keyword%"; $p[] = "%$keyword%"; $p[] = "%$keyword%"; }
        $q .= " ORDER BY rc.date_recette DESC LIMIT 50";
        $stmt = $db->prepare($q); $stmt->execute($p);
        $results = array_merge($results, $stmt->fetchAll());
    }

    // Versements
    if ($searchType === 'all' || $searchType === 'versements') {
        $q = "SELECT 'versement' as source, vb.id, vb.reference, vb.dateversement as date_op, vb.sommeverse as montant, vb.statut, vb.description, a.nomagence as agence_nom, NULL as cat_nom, NULL as immatriculation FROM versement vb LEFT JOIN agence a ON vb.agenceverse_id=a.id LEFT JOIN bank b ON vb.bank_id=b.id WHERE 1=1";
        $p = [];
        if ($dateFrom) { $q .= " AND vb.dateversement >= ?"; $p[] = $dateFrom; }
        if ($dateTo) { $q .= " AND vb.dateversement <= ?"; $p[] = $dateTo; }
        if ($agenceId) { $q .= " AND vb.agenceverse_id = ?"; $p[] = $agenceId; }
        if ($montantMin) { $q .= " AND vb.sommeverse >= ?"; $p[] = $montantMin; }
        if ($montantMax) { $q .= " AND vb.sommeverse <= ?"; $p[] = $montantMax; }
        if ($statut) { $q .= " AND vb.statut = ?"; $p[] = $statut; }
        if ($keyword) { $q .= " AND (vb.reference LIKE ? OR b.nombank LIKE ? OR vb.refversement LIKE ? OR vb.description LIKE ?)"; $p[] = "%$keyword%"; $p[] = "%$keyword%"; $p[] = "%$keyword%"; $p[] = "%$keyword%"; }
        $q .= " ORDER BY vb.dateversement DESC LIMIT 50";
        $stmt = $db->prepare($q); $stmt->execute($p);
        $results = array_merge($results, $stmt->fetchAll());
    }
}

$agences = getAgences($db);
$vehicules = getVehicules($db);
$sourceLabels = ['recette' => 'Recette', 'depense' => 'DÃ©pense', 'recette_camion' => 'Recette camion', 'versement' => 'Versement'];
$sourceColors = ['recette' => 'bg-success', 'depense' => 'bg-danger', 'recette_camion' => 'bg-info', 'versement' => 'bg-primary'];
$sourceLinks = ['recette' => '/recettes/view.php', 'depense' => '/depenses/view.php', 'recette_camion' => '/recettes-camions/view.php', 'versement' => '/versements/view.php'];
?>
<div class="main-content" id="main-content" role="main">
    <header class="main-header" role="banner"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button><span class="mb-0 fw-bold"><?php echo e($pageTitle); ?></span></div><div class="header-right"><div class="dropdown"><button class="notif-btn" aria-label="Notifications" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" role="button" tabindex="0" aria-label="Menu utilisateur" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>DÃ©connexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-search me-2"></i>Recherche MulticritÃ¨re</h1><p class="page-subtitle">Recherchez dans toutes les opÃ©rations financiÃ¨res</p></div></div>

        <div class="card mb-3"><div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2"><label for="date_from" class="form-label">Du</label><input type="date" name="date_from" id="date_from" value="<?php echo e($_GET['date_from'] ?? ''); ?>" class="form-control"></div>
                <div class="col-md-2"><label for="date_to" class="form-label">Au</label><input type="date" name="date_to" id="date_to" value="<?php echo e($_GET['date_to'] ?? ''); ?>" class="form-control"></div>
                <div class="col-md-2"><label for="filter_agence_id" class="form-label">Agence</label><select name="agence_id" id="filter_agence_id" class="form-select"><option value="">Toutes</option><?php foreach($agences as $a): ?><option value="<?php echo $a['id']; ?>" <?php echo (($_GET['agence_id']??'')==$a['id']?'selected':''); ?>><?php echo e($a['nomagence']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Type</label><select name="search_type" class="form-select"><option value="all">Tous</option><option value="recettes" <?php echo (($_GET['search_type']??'')==='recettes'?'selected':''); ?>>Recettes</option><option value="depenses" <?php echo (($_GET['search_type']??'')==='depenses'?'selected':''); ?>>DÃ©penses</option><option value="recettes_camions" <?php echo (($_GET['search_type']??'')==='recettes_camions'?'selected':''); ?>>Recettes camions</option><option value="versements" <?php echo (($_GET['search_type']??'')==='versements'?'selected':''); ?>>Versements</option></select></div>
                <div class="col-md-2"><label class="form-label">Montant min</label><input type="text" inputmode="numeric" class="form-control amount-input" name="montant_min" value="<?php echo e($_GET['montant_min'] ?? ''); ?>" min="0"></div>
                <div class="col-md-2"><label class="form-label">Montant max</label><input type="text" inputmode="numeric" class="form-control amount-input" name="montant_max" value="<?php echo e($_GET['montant_max'] ?? ''); ?>" min="0"></div>
                <div class="col-md-2"><label for="filter_vehicule_id" class="form-label">Camion</label><select name="vehicule_id" id="filter_vehicule_id" class="form-select"><option value="">Tous</option><?php foreach($vehicules as $v): ?><option value="<?php echo $v['id']; ?>" <?php echo (($_GET['vehicule_id']??'')==$v['id']?'selected':''); ?>><?php echo e($v['immatriculation']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label for="filter_statut" class="form-label">Statut</label><select name="statut" id="filter_statut" class="form-select"><option value="">Tous</option><?php foreach(STATUSES as $k=>$l): ?><option value="<?php echo $k; ?>" <?php echo (($_GET['statut']??'')===$k?'selected':''); ?>><?php echo e($l); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Mot-clÃ©</label><input type="text" name="keyword" value="<?php echo e($_GET['keyword'] ?? ''); ?>" class="form-control" placeholder="RÃ©fÃ©rence, description..."></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Rechercher</button></div>
                <div class="col-md-1"><a href="index.php" class="btn btn-outline-secondary w-100" title="RÃ©initialiser"><i class="bi bi-arrow-counterclockwise"></i></a></div>
            </form>
        </div></div>

        <?php if ($searchPerformed): ?>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted"><?php echo count($results); ?> rÃ©sultat(s) trouvÃ©(s)</span>
            <?php if (hasPermission('exports')): ?>
            <button class="btn btn-sm btn-outline-success" data-export-csv="searchTable" data-filename="recherche_<?php echo date('Ymd'); ?>"><i class="bi bi-download me-1"></i>Export CSV</button>
            <?php endif; ?>
        </div>
        <div class="card"><div class="table-container">
            <table class="table" id="searchTable">
                <thead><tr><th>Type</th><th>RÃ©fÃ©rence</th><th>Date</th><th>Montant</th><th>Agence</th><th>CatÃ©gorie</th><th>Camion</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($results as $r): ?>
                <tr>
                    <td><span class="badge <?php echo $sourceColors[$r['source']] ?? 'bg-secondary'; ?>"><?php echo e($sourceLabels[$r['source']] ?? $r['source']); ?></span></td>
                    <td><small class="fw-600"><?php echo e($r['reference']); ?></small></td>
                    <td><?php echo formatDateShort($r['date_op']); ?></td>
                    <td class="fw-bold <?php echo $r['source']==='depense'?'text-danger':'text-success'; ?>"><?php echo formatMoney($r['montant']); ?></td>
                    <td><small><?php
                        if ($r['source'] === 'recette_camion') {
                            $dep = $r['agence_depart_nom'] ?? '';
                            $arr = $r['agence_arrivee_nom'] ?? '';
                            echo e($dep && $arr ? "$dep â†’ $arr" : ($dep ?: ($arr ?: '-')));
                        } else {
                            echo e($r['agence_nom'] ?? '-');
                        }
                    ?></small></td>
                    <td><small><?php echo e($r['cat_nom'] ?? '-'); ?></small></td>
                    <td><small><?php echo e($r['immatriculation'] ?? '-'); ?></small></td>
                    <td><?php echo getStatusBadge($r['statut']); ?></td>
                    <td><a href="<?php echo APP_URL . ($sourceLinks[$r['source']] ?? '#'); ?>?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($results)): ?><tr><td colspan="9" class="text-center text-muted py-4">Aucun rÃ©sultat</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
