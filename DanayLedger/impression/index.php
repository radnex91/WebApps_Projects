<?php
$pageTitle = 'Impression';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('impression');

$db = getDB();

// Rapport à imprimer
$reportType = $_GET['type'] ?? 'journalier';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$agenceId = (int)($_GET['agence_id'] ?? 0) ?: null;

$agenceFilter = $agenceId ? " AND agence_id = $agenceId" : '';
$agenceVerseFilter = $agenceId ? " AND agenceverse_id = $agenceId" : '';

// Stats
$stmt = $db->prepare("SELECT COALESCE(SUM(montantexpedition + montantAccompagnement),0) FROM recette WHERE date BETWEEN ? AND ? AND statut='validee'$agenceFilter");
$stmt->execute([$dateFrom, $dateTo]); $totalRecettes = (float)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM depenses WHERE date_depense BETWEEN ? AND ? AND statut='validee'$agenceFilter");
$stmt->execute([$dateFrom, $dateTo]); $totalDepenses = (float)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM recettes_camions WHERE date_recette BETWEEN ? AND ? AND statut='validee'$agenceFilter");
$stmt->execute([$dateFrom, $dateTo]); $totalRecettesCamions = (float)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(sommeverse),0) FROM versement WHERE dateversement BETWEEN ? AND ? AND statut='validee'$agenceVerseFilter");
$stmt->execute([$dateFrom, $dateTo]); $totalVersements = (float)$stmt->fetchColumn();

// Details
$recettesDetail = $db->prepare("SELECT r.*, (r.montantexpedition + r.montantAccompagnement) as montant, a.nomagence as agence_nom FROM recette r LEFT JOIN agence a ON r.agence_id=a.id WHERE r.date BETWEEN ? AND ? AND r.statut='validee'$agenceFilter ORDER BY r.date");
$recettesDetail->execute([$dateFrom, $dateTo]);

$depensesDetail = $db->prepare("SELECT d.*, c.nom as cat_nom, a.nomagence as agence_nom FROM depenses d LEFT JOIN categories c ON d.categorie_id=c.id LEFT JOIN agence a ON d.agence_id=a.id WHERE d.date_depense BETWEEN ? AND ? AND d.statut='validee'$agenceFilter ORDER BY d.date_depense");
$depensesDetail->execute([$dateFrom, $dateTo]);

$agences = getAgences($db);
$appName = getSystemParam($db, 'app_name', 'DANAY EXPRESS SARL');
?>
<div class="main-content">
    <header class="main-header"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button><h6 class="mb-0 fw-bold"><?php echo e($pageTitle); ?></h6></div><div class="header-right"><div class="dropdown"><button class="notif-btn" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <div class="page-header no-print"><div><h1 class="page-title"><i class="bi bi-printer me-2"></i>Impression</h1></div>
            <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimer</button>
        </div>

        <!-- Print config -->
        <div class="card mb-3 no-print"><div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label">Du</label><input type="date" name="date_from" value="<?php echo e($dateFrom); ?>" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Au</label><input type="date" name="date_to" value="<?php echo e($dateTo); ?>" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Agence</label><select name="agence_id" class="form-select"><option value="">Toutes</option><?php foreach($agences as $a): ?><option value="<?php echo $a['id']; ?>" <?php echo $agenceId==$a['id']?'selected':''; ?>><?php echo e($a['nomagence']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Générer</button></div>
            </form>
        </div></div>

        <!-- Printable Content -->
        <div class="print-content">
            <div class="text-center mb-4">
                <h3 class="fw-bold"><?php echo e($appName); ?></h3>
                <h5>Rapport Financier</h5>
                <p class="text-muted">Période : <?php echo formatDateShort($dateFrom); ?> au <?php echo formatDateShort($dateTo); ?></p>
            </div>

            <!-- Résumé -->
            <div class="row mb-4">
                <div class="col-6"><table class="table table-bordered"><tbody>
                    <tr class="table-success"><th>Total Recettes</th><td class="text-end fw-bold"><?php echo formatMoney($totalRecettes); ?></td></tr>
                    <tr class="table-info"><th>Recettes Camions</th><td class="text-end fw-bold"><?php echo formatMoney($totalRecettesCamions); ?></td></tr>
                    <tr><th>Total Encaissements</th><td class="text-end fw-bold"><?php echo formatMoney($totalRecettes + $totalRecettesCamions); ?></td></tr>
                </tbody></table></div>
                <div class="col-6"><table class="table table-bordered"><tbody>
                    <tr class="table-danger"><th>Total Dépenses</th><td class="text-end fw-bold"><?php echo formatMoney($totalDepenses); ?></td></tr>
                    <tr class="table-primary"><th>Versements Bancaires</th><td class="text-end fw-bold"><?php echo formatMoney($totalVersements); ?></td></tr>
                    <tr class="<?php echo ($totalRecettes+$totalRecettesCamions-$totalDepenses)>=0?'table-success':'table-danger'; ?>"><th>Solde Net</th><td class="text-end fw-bold"><?php echo formatMoney($totalRecettes+$totalRecettesCamions-$totalDepenses); ?></td></tr>
                </tbody></table></div>
            </div>

            <!-- Détail Recettes -->
            <h6 class="fw-bold border-bottom pb-2">Détail des Recettes Journalières</h6>
            <table class="table table-sm table-bordered mb-4">
                <thead><tr><th>Date</th><th>Réf</th><th>Catégorie</th><th>Agence</th><th class="text-end">Montant</th></tr></thead>
                <tbody>
                <?php foreach($recettesDetail->fetchAll() as $r): ?>
                <tr><td><?php echo formatDateShort($r['date']); ?></td><td><?php echo e($r['reference']); ?></td><td><?php echo e($r['cat_nom']??'-'); ?></td><td><?php echo e($r['agence_nom']??'-'); ?></td><td class="text-end"><?php echo formatMoney($r['montant']); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="fw-bold"><td colspan="4">Total Recettes</td><td class="text-end"><?php echo formatMoney($totalRecettes); ?></td></tr></tfoot>
            </table>

            <!-- Détail Dépenses -->
            <h6 class="fw-bold border-bottom pb-2">Détail des Dépenses</h6>
            <table class="table table-sm table-bordered">
                <thead><tr><th>Date</th><th>Réf</th><th>Catégorie</th><th>Agence</th><th>Nature</th><th class="text-end">Montant</th></tr></thead>
                <tbody>
                <?php foreach($depensesDetail->fetchAll() as $d): ?>
                <tr><td><?php echo formatDateShort($d['date_depense']); ?></td><td><?php echo e($d['reference']); ?></td><td><?php echo e($d['cat_nom']??'-'); ?></td><td><?php echo e($d['agence_nom']??'-'); ?></td><td><?php echo e($d['nature']??'-'); ?></td><td class="text-end"><?php echo formatMoney($d['montant']); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="fw-bold"><td colspan="5">Total Dépenses</td><td class="text-end"><?php echo formatMoney($totalDepenses); ?></td></tr></tfoot>
            </table>

            <div class="text-center mt-4 small text-muted">
                <p>Généré le <?php echo formatDate(date('Y-m-d H:i:s')); ?> par <?php echo e($_SESSION['full_name'] ?? ''); ?></p>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>