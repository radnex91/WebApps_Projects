<?php
$pageTitle = 'Rapprochement Financier';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('rapprochement');

$db = getDB();

// Créer un rapprochement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $dateDebut = cleanInput($_POST['date_debut'] ?? '');
        $dateFin = cleanInput($_POST['date_fin'] ?? '');
        $agenceId = (int)($_POST['agence_id'] ?? 0) ?: null;
        $observations = cleanInput($_POST['observations'] ?? '');

        $agenceFilter = $agenceId ? " AND agence_id = $agenceId" : '';
        $agenceVerseFilter = $agenceId ? " AND agenceverse_id = $agenceId" : '';

        $stmt = $db->prepare("SELECT COALESCE(SUM(montantexpedition + montantAccompagnement),0) FROM recette WHERE date BETWEEN ? AND ? AND statut='validee'$agenceFilter");
        $stmt->execute([$dateDebut, $dateFin]); $totalRecettes = (float)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM recettes_camions WHERE date_recette BETWEEN ? AND ? AND statut='validee'$agenceFilter");
        $stmt->execute([$dateDebut, $dateFin]); $totalRecettesCamions = (float)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COALESCE(SUM(sommeverse),0) FROM versement WHERE dateversement BETWEEN ? AND ? AND statut='validee'$agenceVerseFilter");
        $stmt->execute([$dateDebut, $dateFin]); $totalVersements = (float)$stmt->fetchColumn();

        $ecart = ($totalRecettes + $totalRecettesCamions) - $totalVersements;
        $statut = abs($ecart) < 1 ? 'valide' : 'ecart_detecte';

        $stmt = $db->prepare("INSERT INTO rapprochement (date_debut, date_fin, agence_id, total_recettes, total_versements, ecart, statut, observations) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$dateDebut, $dateFin, $agenceId, $totalRecettes + $totalRecettesCamions, $totalVersements, $ecart, $statut, $observations]);
        $newId = $db->lastInsertId();
        addAuditLog('create', 'rapprochement', $newId);
        redirectWithMessage(APP_URL . '/rapprochement/', 'success', 'Rapprochement créé.');
    }
}

// Valider un rapprochement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'validate') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '') && hasPermission('rapprochement_validate')) {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE rapprochement SET statut='valide', validated_by=? WHERE id=?")->execute([$_SESSION['user_id'], $id]);
        addAuditLog('validate', 'rapprochement', $id);
        setFlash('success', 'Rapprochement validé.');
    }
}

$rapprochements = $db->query("SELECT r.*, a.nomagence as agence_nom FROM rapprochement r LEFT JOIN agence a ON r.agence_id=a.id ORDER BY r.created_at DESC LIMIT 50")->fetchAll();
$agences = getAgences($db);
?>
<div class="main-content">
    <header class="main-header"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button><h6 class="mb-0 fw-bold"><?php echo e($pageTitle); ?></h6></div><div class="header-right"><div class="dropdown"><button class="notif-btn" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-scale me-2"></i>Rapprochement Financier</h1><p class="page-subtitle">Comparaison recettes / versements</p></div>
            <?php if (hasPermission('rapprochement_create')): ?><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newModal"><i class="bi bi-plus-lg me-1"></i>Nouveau rapprochement</button><?php endif; ?>
        </div>

        <div class="card"><div class="table-container">
            <table class="table">
                <thead><tr><th>Période</th><th>Agence</th><th>Total Recettes</th><th>Total Versements</th><th>Écart</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rapprochements as $r): ?>
                <tr>
                    <td><?php echo formatDateShort($r['date_debut']); ?> - <?php echo formatDateShort($r['date_fin']); ?></td>
                    <td><?php echo e($r['agence_nom'] ?? 'Toutes'); ?></td>
                    <td class="text-success fw-bold"><?php echo formatMoney($r['total_recettes']); ?></td>
                    <td class="text-primary fw-bold"><?php echo formatMoney($r['total_versements']); ?></td>
                    <td class="fw-bold <?php echo $r['ecart']>=0?'text-success':'text-danger'; ?>"><?php echo formatMoney(abs($r['ecart'])); ?></td>
                    <td><span class="badge <?php echo $r['statut']==='valide'?'bg-success':($r['statut']==='ecart_detecte'?'bg-danger':'bg-warning text-dark'); ?>"><?php echo e($r['statut']==='valide'?'Validé':($r['statut']==='ecart_detecte'?'Écart détecté':'En cours')); ?></span></td>
                    <td>
                        <?php if ($r['statut']==='en_cours' && hasPermission('rapprochement_validate')): ?>
                        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="validate"><input type="hidden" name="id" value="<?php echo $r['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-success" data-confirm="Valider ce rapprochement ?"><i class="bi bi-check-lg"></i></button></form>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-secondary no-print" onclick="window.print()"><i class="bi bi-printer"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($rapprochements)): ?><tr><td colspan="7" class="text-center text-muted py-4">Aucun rapprochement</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>

<!-- Modal Nouveau Rapprochement -->
<div class="modal fade" id="newModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Nouveau rapprochement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="create">
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Date début <span class="text-danger">*</span></label><input type="date" name="date_debut" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Date fin <span class="text-danger">*</span></label><input type="date" name="date_fin" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Agence</label><select name="agence_id" class="form-select"><option value="">Toutes les agences</option><?php foreach($agences as $a): ?><option value="<?php echo $a['id']; ?>"><?php echo e($a['nomagence']); ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">Observations</label><textarea name="observations" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Créer le rapprochement</button></div>
    </form>
</div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>