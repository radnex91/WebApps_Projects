<?php
$pageTitle = 'Rapports';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('rapports');

$db = getDB();
$type = $_GET['type'] ?? 'journalier';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$agenceId = (int)($_GET['agence_id'] ?? 0) ?: null;

// Defaults per type
if ($type === 'hebdomadaire' && empty($_GET['date_from'])) {
    $dateFrom = date('Y-m-d', strtotime('monday this week'));
    $dateTo = date('Y-m-d', strtotime('sunday this week'));
} elseif ($type === 'mensuel' && empty($_GET['date_from'])) {
    $dateFrom = date('Y-m-01');
    $dateTo = date('Y-m-t');
}

$agenceFilter = $agenceId ? " AND agence_id = $agenceId" : '';
$agenceVerseFilter = $agenceId ? " AND agenceverse_id = $agenceId" : '';

// Recettes
$stmt = $db->prepare("SELECT COALESCE(SUM(montantexpedition + montantAccompagnement),0) FROM recette WHERE date BETWEEN ? AND ? AND statut='validee'$agenceFilter");
$stmt->execute([$dateFrom, $dateTo]); $totalRecettes = (float)$stmt->fetchColumn();

// Dépenses
$stmt = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM depenses WHERE date_depense BETWEEN ? AND ? AND statut='validee'$agenceFilter");
$stmt->execute([$dateFrom, $dateTo]); $totalDepenses = (float)$stmt->fetchColumn();

// Recettes camions
$stmt = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM recettes_camions WHERE date_recette BETWEEN ? AND ? AND statut='validee'$agenceFilter");
$stmt->execute([$dateFrom, $dateTo]); $totalRecettesCamions = (float)$stmt->fetchColumn();

// Versements
$stmt = $db->prepare("SELECT COALESCE(SUM(sommeverse),0) FROM versement WHERE dateversement BETWEEN ? AND ? AND statut='validee'$agenceVerseFilter");
$stmt->execute([$dateFrom, $dateTo]); $totalVersements = (float)$stmt->fetchColumn();

$solde = $totalRecettes + $totalRecettesCamions - $totalDepenses;

// Détail par catégorie (recettes)
$detailRecettes = $db->prepare("SELECT SUM(r.montantexpedition + r.montantAccompagnement) as total FROM recette r WHERE r.date BETWEEN ? AND ? AND r.statut='validee'$agenceFilter");
$detailRecettes->execute([$dateFrom, $dateTo]); $recettesParCat = $detailRecettes->fetchAll();

// Détail par catégorie (dépenses)
$detailDepenses = $db->prepare("SELECT c.nom as cat, SUM(d.montant) as total FROM depenses d LEFT JOIN categories c ON d.categorie_id=c.id WHERE d.date_depense BETWEEN ? AND ? AND d.statut='validee'$agenceFilter GROUP BY d.categorie_id ORDER BY total DESC");
$detailDepenses->execute([$dateFrom, $dateTo]); $depensesParCat = $detailDepenses->fetchAll();

// Détail par camion
$detailCamions = $db->prepare("SELECT v.immatriculation, SUM(rc.montant) as total FROM recettes_camions rc LEFT JOIN vehicules v ON rc.vehicule_id=v.id WHERE rc.date_recette BETWEEN ? AND ? AND rc.statut='validee'$agenceFilter GROUP BY rc.vehicule_id ORDER BY total DESC");
$detailCamions->execute([$dateFrom, $dateTo]); $camionData = $detailCamions->fetchAll();

// Détail par agence
$detailAgences = $db->prepare("SELECT a.nomagence, COALESCE((SELECT SUM(r.montantexpedition + r.montantAccompagnement) FROM recette r WHERE r.agence_id=a.id AND r.date BETWEEN ? AND ? AND r.statut='validee'),0) as recettes, COALESCE((SELECT SUM(d.montant) FROM depenses d WHERE d.agence_id=a.id AND d.date_depense BETWEEN ? AND ? AND d.statut='validee'),0) as depenses FROM agence a WHERE a.statut='actif' ORDER BY a.nomagence");
$detailAgences->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]); $agenceData = $detailAgences->fetchAll();

$agences = getAgences($db);
?>
<div class="main-content" id="main-content" role="main">
    <header class="main-header" role="banner"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button><span class="mb-0 fw-bold"><?php echo e($pageTitle); ?></span></div><div class="header-right"><div class="dropdown"><button class="notif-btn" aria-label="Notifications" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" role="button" tabindex="0" aria-label="Menu utilisateur" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <div class="page-header">
            <div><h1 class="page-title"><i class="bi bi-bar-chart-fill me-2"></i>Rapports</h1></div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary no-print" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimer</button>
                <?php if (hasPermission('exports')): ?>
                <a href="<?php echo APP_URL; ?>/exports/?type=rapport&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>&agence_id=<?php echo $agenceId ?? ''; ?>" class="btn btn-outline-success"><i class="bi bi-download me-1"></i>Exporter</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filtres -->
        <div class="card mb-3"><div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2"><label for="report_type" class="form-label">Type</label><select name="type" id="report_type" class="form-select"><option value="journalier" <?php echo $type==='journalier'?'selected':''; ?>>Journalier</option><option value="hebdomadaire" <?php echo $type==='hebdomadaire'?'selected':''; ?>>Hebdomadaire</option><option value="mensuel" <?php echo $type==='mensuel'?'selected':''; ?>>Mensuel</option><option value="agence" <?php echo $type==='agence'?'selected':''; ?>>Par agence</option><option value="camion" <?php echo $type==='camion'?'selected':''; ?>>Par camion</option></select></div>
                <div class="col-md-2"><label for="date_from" class="form-label">Du</label><input type="date" name="date_from" id="date_from" value="<?php echo e($dateFrom); ?>" class="form-control"></div>
                <div class="col-md-2"><label for="date_to" class="form-label">Au</label><input type="date" name="date_to" id="date_to" value="<?php echo e($dateTo); ?>" class="form-control"></div>
                <div class="col-md-2"><label for="filter_agence_id" class="form-label">Agence</label><select name="agence_id" id="filter_agence_id" class="form-select"><option value="">Toutes</option><?php foreach($agences as $a): ?><option value="<?php echo $a['id']; ?>" <?php echo $agenceId==$a['id']?'selected':''; ?>><?php echo e($a['nomagence']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Générer</button></div>
            </form>
        </div></div>

        <!-- Résumé -->
        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="stat-card bg-success-gradient"><div class="stat-label">Total Recettes</div><div class="stat-value"><?php echo formatMoney($totalRecettes); ?></div></div></div>
            <div class="col-md-3"><div class="stat-card bg-danger-gradient"><div class="stat-label">Total Dépenses</div><div class="stat-value"><?php echo formatMoney($totalDepenses); ?></div></div></div>
            <div class="col-md-3"><div class="stat-card bg-info-gradient"><div class="stat-label">Recettes Camions</div><div class="stat-value"><?php echo formatMoney($totalRecettesCamions); ?></div></div></div>
            <div class="col-md-3"><div class="stat-card <?php echo $solde>=0?'bg-primary-gradient':'bg-danger-gradient'; ?>"><div class="stat-label">Solde net</div><div class="stat-value"><?php echo formatMoney(abs($solde)); ?></div></div></div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6"><div class="stat-card bg-primary-gradient"><div class="stat-label">Versements bancaires</div><div class="stat-value"><?php echo formatMoney($totalVersements); ?></div></div></div>
            <div class="col-md-6"><div class="stat-card bg-warning-gradient"><div class="stat-label">Écart (recettes - versements)</div><div class="stat-value"><?php echo formatMoney(abs($totalRecettes + $totalRecettesCamions - $totalVersements)); ?></div></div></div>
        </div>

        <!-- Graphiques & Détails -->
        <div class="row g-3">
            <?php if ($type === 'agence' || $type === 'journalier'): ?>
            <div class="col-lg-6">
                <div class="card"><div class="card-header"><i class="bi bi-building me-2"></i>Par Agence</div><div class="card-body">
                    <table class="table table-sm"><thead><tr><th>Agence</th><th>Recettes</th><th>Dépenses</th><th>Solde</th></tr></thead><tbody>
                    <?php foreach($agenceData as $ad): ?><tr><td><?php echo e($ad['nomagence']); ?></td><td class="text-success"><?php echo formatMoney($ad['recettes']); ?></td><td class="text-danger"><?php echo formatMoney($ad['depenses']); ?></td><td class="fw-bold <?php echo ($ad['recettes']-$ad['depenses'])>=0?'text-success':'text-danger'; ?>"><?php echo formatMoney($ad['recettes']-$ad['depenses']); ?></td></tr><?php endforeach; ?>
                    </tbody></table>
                </div></div>
            </div>
            <?php endif; ?>

            <?php if ($type === 'camion'): ?>
            <div class="col-lg-6">
                <div class="card"><div class="card-header"><i class="bi bi-truck me-2"></i>Par Camion</div><div class="card-body">
                    <table class="table table-sm"><thead><tr><th>Camion</th><th>Recettes</th></tr></thead><tbody>
                    <?php foreach($camionData as $cd): ?><tr><td><?php echo e($cd['immatriculation'] ?? 'Non assigné'); ?></td><td class="text-success fw-bold"><?php echo formatMoney($cd['total']); ?></td></tr><?php endforeach; ?>
                    </tbody></table>
                </div></div>
            </div>
            <?php endif; ?>

            <div class="col-lg-6">
                <div class="card"><div class="card-header"><i class="bi bi-cash-coin me-2"></i>Recettes par catégorie</div><div class="card-body">
                    <div class="chart-container" style="height:250px"><canvas id="chartRecettes" role="img" aria-label="Graphique des recettes par catégorie"></canvas></div>
                    <table class="table table-sm mt-2"><tbody><?php foreach($recettesParCat as $rc): ?><tr><td><?php echo e($rc['cat'] ?? 'Non classé'); ?></td><td class="text-success"><?php echo formatMoney($rc['total']); ?></td></tr><?php endforeach; ?></tbody></table>
                </div></div>
            </div>
            <div class="col-lg-6">
                <div class="card"><div class="card-header"><i class="bi bi-cart-dash me-2"></i>Dépenses par catégorie</div><div class="card-body">
                    <div class="chart-container" style="height:250px"><canvas id="chartDepenses" role="img" aria-label="Graphique des dépenses par catégorie"></canvas></div>
                    <table class="table table-sm mt-2"><tbody><?php foreach($depensesParCat as $dc): ?><tr><td><?php echo e($dc['cat'] ?? 'Non classé'); ?></td><td class="text-danger"><?php echo formatMoney($dc['total']); ?></td></tr><?php endforeach; ?></tbody></table>
                </div></div>
            </div>
        </div>
    </div>
</div>

<script>
// Charts
const recettesLabels = <?php echo json_encode(array_column($recettesParCat, 'cat') ?: ['Aucune donnée']); ?>;
const recettesValues = <?php echo json_encode(array_column($recettesParCat, 'total') ?: [0]); ?>;
const depensesLabels = <?php echo json_encode(array_column($depensesParCat, 'cat') ?: ['Aucune donnée']); ?>;
const depensesValues = <?php echo json_encode(array_column($depensesParCat, 'total') ?: [0]); ?>;

new Chart(document.getElementById('chartRecettes'), {
    type: 'doughnut',
    data: { labels: recettesLabels, datasets: [{ data: recettesValues, backgroundColor: ['#198754','#20c997','#0dcaf0','#ffc107','#fd7e14','#6f42c1','#d63384'] }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
});
new Chart(document.getElementById('chartDepenses'), {
    type: 'doughnut',
    data: { labels: depensesLabels, datasets: [{ data: depensesValues, backgroundColor: ['#dc3545','#fd7e14','#ffc107','#6c757d','#0dcaf0','#198754','#6f42c1'] }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>