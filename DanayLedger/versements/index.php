<?php
// AJAX endpoint: fetch recette total for agence on a given date
if (isset($_GET['action']) && $_GET['action'] === 'get_recette_agence') {
    require_once __DIR__ . '/../config/database.php';
    $db = getDB();
    header('Content-Type: application/json');
    $agenceId = (int)($_GET['agence_id'] ?? 0);
    $date = $_GET['date'] ?? '';
    if ($agenceId <= 0 || empty($date)) {
        echo json_encode(['montant' => null]);
        exit;
    }
    $stmt = $db->prepare("SELECT COALESCE(SUM(montantexpedition + montantAccompagnement), 0) AS total FROM recette WHERE agence_id = ? AND date = ? AND statut = 'validee'");
    $stmt->execute([$agenceId, $date]);
    $total = (float)$stmt->fetchColumn();
    echo json_encode(['montant' => $total]);
    exit;
}

$pageTitle = 'Versements Bancaires';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('versements');

$db = getDB();
$agences = getAgences($db);
$banks = getBanks($db);
$filterDateFrom = $_GET['date_from'] ?? date('Y-m-01');
$filterDateTo = $_GET['date_to'] ?? date('Y-m-t');
$filterAgence = $_GET['agence_id'] ?? '';
$filterStatut = $_GET['statut'] ?? '';
$filterSearch = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$currentPage = $page;

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    rateLimit('versements_' . ($action ?: 'crud'));

    if ($action === 'create' && hasPermission('versements_create')) {
        $dateversement = cleanInput($_POST['dateversement'] ?? '');
        $refversement = cleanInput($_POST['refversement'] ?? '');
        $sommeverse = (float)str_replace([' ', "\u{00A0}", "\u{202F}"], '', $_POST['sommeverse'] ?? '0');
        $agenceverse_id = (int)($_POST['agenceverse_id'] ?? 0) ?: null;
        $bank_id = (int)($_POST['bank_id'] ?? 0) ?: null;
        $nomoperateur = $_SESSION['full_name'] ?? '';
        $nomediteur = '';
        $dateedite = null;
        $recettejourneeagence = (float)($_POST['recettejourneeagence'] ?? 0) ?: null;
        $ecart = $recettejourneeagence ? ($sommeverse - $recettejourneeagence) : null;
        $description = cleanInput($_POST['description'] ?? '');

        if (empty($dateversement) || $sommeverse <= 0) {
            setFlash('error', 'La date et le montant sont obligatoires.');
        } else {
            $reference = generateReference('VRS');
            try {
                $stmt = $db->prepare("INSERT INTO versement (reference, dateversement, refversement, sommeverse, agenceverse_id, bank_id, nomoperateur, nomediteur, dateedite, recettejourneeagence, ecart, description, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$reference, $dateversement, $refversement, $sommeverse, $agenceverse_id, $bank_id, $nomoperateur, $nomediteur, $dateedite, $recettejourneeagence, $ecart, $description, $_SESSION['user_id']]);
                $newId = $db->lastInsertId();

                if (isset($_FILES['justificatif']) && $_FILES['justificatif']['error'] === UPLOAD_ERR_OK) {
                    $path = uploadJustificatif($_FILES['justificatif'], 'versement', $newId);
                    if ($path) $db->prepare("UPDATE versement SET justificatif_path = ? WHERE id = ?")->execute([$path, $newId]);
                }

                addAuditLog('create', 'versement', $newId, [], ['reference' => $reference, 'sommeverse' => $sommeverse]);
                setFlash('success', 'Versement créé avec succès.');
            } catch (Exception $e) { setFlash('error', 'Erreur : ' . $e->getMessage()); }
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
        exit;
    }

    if ($action === 'update' && hasPermission('versements_edit')) {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM versement WHERE id = ?");
        $stmt->execute([$id]);
        $versement = $stmt->fetch();
        if (!$versement || $versement['statut'] !== 'en_attente') {
            setFlash('error', 'Versement introuvable ou déjà traité.');
        } elseif (!hasPermission('admin') && (int)$versement['created_by'] !== (int)$_SESSION['user_id']) {
            setFlash('error', 'Vous n\'êtes pas autorisé à modifier ce versement.');
        } else {
            $oldValues = $versement;
            $dateversement = cleanInput($_POST['dateversement'] ?? '');
            $refversement = cleanInput($_POST['refversement'] ?? '');
            $sommeverse = (float)str_replace([' ', "\u{00A0}", "\u{202F}"], '', $_POST['sommeverse'] ?? '0');
            $agenceverse_id = (int)($_POST['agenceverse_id'] ?? 0) ?: null;
            $bank_id = (int)($_POST['bank_id'] ?? 0) ?: null;
            $nomediteur = $_SESSION['full_name'] ?? '';
            $dateedite = date('Y-m-d');
            $recettejourneeagence = (float)($_POST['recettejourneeagence'] ?? 0) ?: null;
            $ecart = $recettejourneeagence ? ($sommeverse - $recettejourneeagence) : null;
            $description = cleanInput($_POST['description'] ?? '');

            if (empty($dateversement) || $sommeverse <= 0) {
                setFlash('error', 'La date et le montant sont obligatoires.');
            } else {
                try {
                    $stmt = $db->prepare("UPDATE versement SET dateversement=?, refversement=?, sommeverse=?, agenceverse_id=?, bank_id=?, nomediteur=?, dateedite=?, recettejourneeagence=?, ecart=?, description=? WHERE id=?");
                    $stmt->execute([$dateversement, $refversement, $sommeverse, $agenceverse_id, $bank_id, $nomediteur, $dateedite, $recettejourneeagence, $ecart, $description, $id]);
                    if (isset($_FILES['justificatif']) && $_FILES['justificatif']['error'] === UPLOAD_ERR_OK) {
                        $path = uploadJustificatif($_FILES['justificatif'], 'versement', $id);
                        if ($path) $db->prepare("UPDATE versement SET justificatif_path = ? WHERE id = ?")->execute([$path, $id]);
                    }
                    addAuditLog('update', 'versement', $id, $oldValues, ['sommeverse'=>$sommeverse,'dateversement'=>$dateversement]);
                    setFlash('success', 'Versement modifié.');
                } catch (Exception $e) { setFlash('error', 'Erreur : ' . $e->getMessage()); }
            }
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
        exit;
    }

    if ($action === 'validate' && hasPermission('versements_validate')) {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE versement SET statut='validee', validated_by=? WHERE id=? AND statut='en_attente'")->execute([$_SESSION['user_id'], $id]);
        addAuditLog('validate', 'versement', $id);
        setFlash('success', 'Versement validé.');
    } elseif ($action === 'cancel' && hasPermission('versements_validate')) {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE versement SET statut='annulee', validated_by=? WHERE id=? AND statut='en_attente'")->execute([$_SESSION['user_id'], $id]);
        addAuditLog('cancel', 'versement', $id);
        setFlash('warning', 'Versement annulÃ©.');
    } elseif ($action === 'delete' && hasPermission('versements_delete')) {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM versement WHERE id = ?");
        $stmt->execute([$id]);
        $oldRecord = $stmt->fetch();
        if ($oldRecord) {
            if (!empty($oldRecord['justificatif_path'])) {
                $filePath = UPLOAD_DIR . $oldRecord['justificatif_path'];
                if (file_exists($filePath)) @unlink($filePath);
            }
            $db->prepare("DELETE FROM versement WHERE id = ?")->execute([$id]);
            addAuditLog('delete', 'versement', $id, [], $oldRecord);
            setFlash('success', 'Versement supprimÃ©.');
        } else {
            setFlash('error', 'Versement introuvable.');
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
    exit;
}

$where = ["v.dateversement BETWEEN ? AND ?"];
$params = [$filterDateFrom, $filterDateTo];
if ($filterAgence) { $where[] = "v.agenceverse_id = ?"; $params[] = $filterAgence; }
if ($filterStatut) { $where[] = "v.statut = ?"; $params[] = $filterStatut; }
if ($filterSearch) { $where[] = "(v.reference LIKE ? OR v.refversement LIKE ? OR v.nomoperateur LIKE ?)"; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; }

$whereClause = implode(' AND ', $where);
$query = "SELECT v.*, a.nomagence, b.nombank FROM versement v LEFT JOIN agence a ON v.agenceverse_id=a.id LEFT JOIN bank b ON v.bank_id=b.id WHERE $whereClause ORDER BY v.dateversement DESC, v.created_at DESC";
$result = paginate($db, $query, $params, $page);
$totalMontant = array_sum(array_column($result['data'], 'sommeverse'));
?>

<div class="main-content" id="main-content" role="main">
    <header class="main-header" role="banner">
        <div class="header-left"><button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button><span class="mb-0 fw-bold"><?php echo e($pageTitle); ?></span></div>
        <div class="header-right"><div class="dropdown"><button class="notif-btn" aria-label="Notifications" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" role="button" tabindex="0" aria-label="Menu utilisateur" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div>
    </header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header">
            <div><h1 class="page-title"><i class="bi bi-bank2 me-2"></i>Versements Bancaires</h1><p class="page-subtitle">Suivi des versements en banque</p></div>
            <?php if (hasPermission('versements_create')): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Nouveau versement</button>
            <?php endif; ?>
        </div>

        <div class="card mb-3"><div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2"><label for="date_from" class="form-label">Du</label><input type="date" name="date_from" id="date_from" value="<?php echo e($filterDateFrom); ?>" class="form-control"></div>
                <div class="col-md-2"><label for="date_to" class="form-label">Au</label><input type="date" name="date_to" id="date_to" value="<?php echo e($filterDateTo); ?>" class="form-control"></div>
                <div class="col-md-2"><label for="filter_agence_id" class="form-label">Agence</label><select name="agence_id" id="filter_agence_id" class="form-select"><option value="">Toutes</option><?php foreach($agences as $a): ?><option value="<?php echo $a['id']; ?>" <?php echo $filterAgence == $a['id'] ? 'selected' : ''; ?>><?php echo e($a['nomagence']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label for="filter_statut" class="form-label">Statut</label><select name="statut" id="filter_statut" class="form-select"><option value="">Tous</option><?php foreach(STATUSES as $k=>$l): ?><option value="<?php echo $k; ?>" <?php echo $filterStatut===$k?'selected':''; ?>><?php echo e($l); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label for="filter_search" class="form-label">Recherche</label><input type="text" name="search" id="filter_search" value="<?php echo e($filterSearch); ?>" class="form-control" placeholder="Réf, opérateur..."></div>
                <div class="col-md-1"><button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i></button></div>
            </form>
        </div></div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted">Total : <strong class="text-primary"><?php echo formatMoney($totalMontant); ?></strong></span>
            <span class="text-muted"><?php echo $result['total']; ?> résultat(s)</span>
        </div>

        <div class="card"><div class="table-container">
            <table class="table" aria-label="Liste des versements">
                <thead><tr><th>Référence</th><th>Date</th><th>Banque</th><th>Montant versé</th><th>Agence</th><th>Opérateur</th><th>Écart</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($result['data'] as $v): ?>
                <tr>
                    <td><small class="fw-600"><?php echo e($v['reference']); ?></small></td>
                    <td><?php echo formatDateShort($v['dateversement']); ?></td>
                    <td><small><?php echo e($v['nombank'] ?? '-'); ?></small></td>
                    <td class="fw-bold text-primary"><?php echo formatMoney($v['sommeverse']); ?></td>
                    <td><small><?php echo e($v['nomagence'] ?? '-'); ?></small></td>
                    <td><small><?php echo e($v['nomoperateur'] ?? '-'); ?></small></td>
                    <td><?php echo formatMoney($v['ecart'] ?? 0); ?></td>
                    <td><?php echo getStatusBadge($v['statut']); ?></td>
                    <td><div class="action-btns">
                        <a href="view.php?id=<?php echo $v['id']; ?>" class="btn btn-sm btn-outline-primary" title="Voir"><i class="bi bi-eye"></i></a>
                        <?php if ($v['statut']==='en_attente' && hasPermission('versements_edit')): ?>
                        <button type="button" class="btn btn-sm btn-outline-warning btn-edit-versement" title="Modifier"
                            data-id="<?php echo $v['id']; ?>"
                            data-dateversement="<?php echo e($v['dateversement']); ?>"
                            data-refversement="<?php echo e($v['refversement'] ?? ''); ?>"
                            data-sommeverse="<?php echo $v['sommeverse']; ?>"
                            data-agenceverse_id="<?php echo $v['agenceverse_id'] ?? ''; ?>"
                            data-bank_id="<?php echo $v['bank_id'] ?? ''; ?>"
                            data-nomoperateur="<?php echo e($v['nomoperateur'] ?? ''); ?>"
                            data-recettejourneeagence="<?php echo $v['recettejourneeagence'] ?? ''; ?>"
                            data-description="<?php echo e($v['description'] ?? ''); ?>"
                            data-justificatif="<?php echo e($v['justificatif_path'] ?? ''); ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php endif; ?>
                        <?php if ($v['statut']==='en_attente' && hasPermission('versements_validate')): ?>
                        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="id" value="<?php echo $v['id']; ?>"><input type="hidden" name="action" value="validate"><button type="submit" class="btn btn-sm btn-outline-success" aria-label="Valider" data-confirm="Valider ?"><i class="bi bi-check-lg"></i></button></form>
                        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="id" value="<?php echo $v['id']; ?>"><input type="hidden" name="action" value="cancel"><button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Supprimer" data-confirm="Annuler ?"><i class="bi bi-x-lg"></i></button></form>
                        <?php endif; ?>
                        <?php if (hasPermission('versements_delete')): ?>
                        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="id" value="<?php echo $v['id']; ?>"><input type="hidden" name="action" value="delete"><button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Supprimer" data-confirm="Supprimer ce versement ?" title="Supprimer"><i class="bi bi-trash"></i></button></form>
                        <?php endif; ?>
                    </div></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($result['data'])): ?><tr><td colspan="9" class="text-center text-muted py-4">Aucun versement trouvé</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
        <?php echo renderPagination($result['page'], $result['totalPages'], '?' . http_build_query(array_filter(['date_from'=>$filterDateFrom,'date_to'=>$filterDateTo,'agence_id'=>$filterAgence,'statut'=>$filterStatut,'search'=>$filterSearch]))); ?>
    </div>
</div>

<!-- Modal Créer Versement -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="addModalLabel"><i class="bi bi-bank2 me-2"></i>Nouveau Versement</h5><button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="modal"></button></div>
    <form method="POST" enctype="multipart/form-data" data-validate id="createForm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
            <div id="createAlert" class="alert" style="display:none;"></div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Date versement <span class="text-danger">*</span></label>
                    <input type="date" name="dateversement" id="create_dateversement" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Somme versée (FCFA) <span class="text-danger">*</span></label>
                    <input type="number" name="sommeverse" id="create_sommeverse" class="form-control" min="0" step="1" required placeholder="0">
                </div>
                <div class="col-md-4">
                    <label for="create_refversement" class="form-label">Réf. versement</label><input type="text" name="refversement" id="create_refversement" class="form-control" placeholder="Référence optionnelle">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Agence <span class="text-danger">*</span></label>
                    <select name="agenceverse_id" id="create_agenceverse_id" class="form-select" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach($agences as $a): ?>
                        <option value="<?php echo $a['id']; ?>"><?php echo e($a['nomagence']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="create_bank_id" class="form-label">Banque</label><select              <select name="bank_id" id="create_bank_id" class="form-select">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach($banks as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo e($b['nombank']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4" style="display:none;">
                    <label for="create_nomoperateur" class="form-label">Opérateur de saisie</label><input type="text" name="nomoperateur" id="create_nomoperateur" class="form-control" readonly style="background-color:var(--surface-alt);">
                </div>
                <div class="col-md-4">
                    <label for="create_recettejourneeagence" class="form-label">Recette journée agence (FCFA)</label><input type="text" id="create_recettejourneeagence" class="form-control" readonly style="background-color:var(--surface-alt);" placeholder="Sélectionnez une agence et une date">
                    <input type="hidden" name="recettejourneeagence" id="create_recettejourneeagence_hidden" value="">
                    <small class="text-muted">Calculée automatiquement selon l'agence et la date</small>
                </div>
                <div class="col-md-4">
                    <label for="create_ecart" class="form-label">Écart (FCFA)</label><input type="text" id="create_ecart" class="form-control" readonly style="background-color:var(--surface-alt);" placeholder="-">
                    <small class="text-muted">Calculé automatiquement</small>
                </div>
                <div class="col-md-6">
                    <label for="create_justificatif" class="form-label">Justificatif</label><input type="file" name="justificatif" id="create_justificatif" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                </div>
                <div class="col-12">
                    <label for="create_description" class="form-label">Observation</label><textarea            <textarea name="description" id="create_description" class="form-control" rows="3" placeholder="Observation sur le versement..."></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary" id="createSubmitBtn"><i class="bi bi-check-lg me-1"></i>Enregistrer & Nouveau</button>
        </div>
    </form>
</div></div></div>

<!-- Modal Modifier Versement -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="editModalLabel"><i class="bi bi-pencil me-2"></i>Modifier Versement</h5><button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="modal"></button></div>
    <form method="POST" enctype="multipart/form-data" data-validate>
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Date versement <span class="text-danger">*</span></label>
                    <input type="date" name="dateversement" id="edit_dateversement" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Somme versée (FCFA) <span class="text-danger">*</span></label>
                    <input type="number" name="sommeverse" id="edit_sommeverse" class="form-control" min="0" step="1" required>
                </div>
                <div class="col-md-4">
                    <label for="edit_refversement" class="form-label">Réf. versement</label><input type="text" name="refversement" id="edit_refversement" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Agence <span class="text-danger">*</span></label>
                    <select name="agenceverse_id" id="edit_agenceverse_id" class="form-select" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach($agences as $a): ?>
                        <option value="<?php echo $a['id']; ?>"><?php echo e($a['nomagence']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="edit_bank_id" class="form-label">Banque</label><select              <select name="bank_id" id="edit_bank_id" class="form-select">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach($banks as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo e($b['nombank']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4" style="display:none;">
                    <label for="edit_nomoperateur" class="form-label">Opérateur de saisie</label><input type="text" id="edit_nomoperateur" class="form-control" readonly style="background-color:var(--surface-alt);">
                </div>
                <div class="col-md-4">
                    <label for="edit_recettejourneeagence" class="form-label">Éditeur</label>
                    <input type="text" class="form-control" value="<?php echo e($_SESSION['full_name'] ?? ''); ?>" readonly style="background-color:var(--surface-alt);">
                    <small class="text-muted">Renseigné automatiquement</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date édition</label>
                    <input type="text" class="form-control" value="<?php echo date('d/m/Y'); ?>" readonly style="background-color:var(--surface-alt);">
                    <small class="text-muted">Renseignée automatiquement</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Recette journée agence (FCFA)</label><input type="text" id="edit_recettejourneeagence" class="form-control" readonly style="background-color:var(--surface-alt);" placeholder="Sélectionnez une agence et une date">
                    <input type="hidden" name="recettejourneeagence" id="edit_recettejourneeagence_hidden" value="">
                    <small class="text-muted">Calculée automatiquement selon l'agence et la date</small>
                </div>
                <div class="col-md-4">
                    <label for="edit_ecart" class="form-label">Écart (FCFA)</label><input type="text" id="edit_ecart" class="form-control" readonly style="background-color:var(--surface-alt);" placeholder="-">
                    <small class="text-muted">Calculé automatiquement</small>
                </div>
                <div class="col-md-6">
                    <label for="edit_description" class="form-label">Nouveau justificatif</label>
                    <input type="file" name="justificatif" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    <small class="text-muted" id="edit_justificatif_info"></small>
                </div>
                <div class="col-12">
                    <label class="form-label">Observation</label><textarea            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
        </div>
    </form>
</div></div></div>

<script>
// Format number as FCFA
function formatFCFA(n) {
    if (n === null || isNaN(n)) return '-';
    return Number(n).toLocaleString('fr-FR') + ' FCFA';
}

// Fetch recette for agence + date and update fields
function fetchRecetteAgence(modalPrefix) {
    var dateEl = document.getElementById(modalPrefix + '_dateversement');
    var agenceEl = document.getElementById(modalPrefix + '_agenceverse_id');
    var recetteEl = document.getElementById(modalPrefix + '_recettejourneeagence');
    var recetteHiddenEl = document.getElementById(modalPrefix + '_recettejourneeagence_hidden');
    var ecartEl = document.getElementById(modalPrefix + '_ecart');
    var sommeverseEl = document.getElementById(modalPrefix + '_sommeverse');

    var date = dateEl ? dateEl.value : '';
    var agenceId = agenceEl ? agenceEl.value : '';

    if (!date || !agenceId) {
        recetteEl.value = '';
        if (recetteHiddenEl) recetteHiddenEl.value = '';
        ecartEl.value = '';
        return;
    }

    fetch('<?php echo APP_URL; ?>/versements/index.php?action=get_recette_agence&agence_id=' + agenceId + '&date=' + date, {
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var montant = data.montant;
        if (montant !== null && montant > 0) {
            recetteEl.value = formatFCFA(montant);
            if (recetteHiddenEl) recetteHiddenEl.value = montant;
            // Calculate ecart
            var sommeverse = sommeverseEl ? parseFloat(sommeverseEl.value) || 0 : 0;
            var ecart = sommeverse - montant;
            ecartEl.value = formatFCFA(ecart);
            ecartEl.style.color = ecart < 0 ? '#dc3545' : '#198754';
        } else {
            recetteEl.value = 'Aucune recette trouvée';
            if (recetteHiddenEl) recetteHiddenEl.value = '';
            ecartEl.value = '';
        }
    })
    .catch(function() {
        recetteEl.value = 'Erreur de chargement';
        if (recetteHiddenEl) recetteHiddenEl.value = '';
        ecartEl.value = '';
    });
}

// Calculate ecart when sommeverse changes
function calcEcart(modalPrefix) {
    var sommeverseEl = document.getElementById(modalPrefix + '_sommeverse');
    var recetteHiddenEl = document.getElementById(modalPrefix + '_recettejourneeagence_hidden');
    var ecartEl = document.getElementById(modalPrefix + '_ecart');
    var sommeverse = sommeverseEl ? parseFloat(sommeverseEl.value) || 0 : 0;
    var recette = recetteHiddenEl ? parseFloat(recetteHiddenEl.value) || 0 : 0;
    if (recette > 0) {
        var ecart = sommeverse - recette;
        ecartEl.value = formatFCFA(ecart);
        ecartEl.style.color = ecart < 0 ? '#dc3545' : '#198754';
    }
}

// Create modal - auto fetch recette
document.getElementById('create_agenceverse_id')?.addEventListener('change', function() { fetchRecetteAgence('create'); });
document.getElementById('create_dateversement')?.addEventListener('change', function() { fetchRecetteAgence('create'); });
document.getElementById('create_sommeverse')?.addEventListener('input', function() { calcEcart('create'); });

// Edit modal - auto fetch recette
document.getElementById('edit_agenceverse_id')?.addEventListener('change', function() { fetchRecetteAgence('edit'); });
document.getElementById('edit_dateversement')?.addEventListener('change', function() { fetchRecetteAgence('edit'); });
document.getElementById('edit_sommeverse')?.addEventListener('input', function() { calcEcart('edit'); });

// Reset create form for new entry (keep date and operator)
function resetCreateForm() {
    var form = document.getElementById('createForm');
    form.querySelector('[name="sommeverse"]').value = '';
    form.querySelector('[name="refversement"]').value = '';
    form.querySelector('[name="agenceverse_id"]').value = '';
    form.querySelector('[name="bank_id"]').value = '';
    form.querySelector('[name="recettejourneeagence"]').value = '';
    document.getElementById('create_recettejourneeagence').value = '';
    document.getElementById('create_recettejourneeagence_hidden').value = '';
    document.getElementById('create_ecart').value = '';
    form.querySelector('[name="description"]').value = '';
    var fileInput = document.getElementById('create_justificatif');
    if (fileInput) fileInput.value = '';
}

// AJAX submit for create form — keep modal open for continuous data entry
document.getElementById('createForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this;
    var alertDiv = document.getElementById('createAlert');
    var btn = document.getElementById('createSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enregistrement...';
    alertDiv.style.display = 'none';

    fetch(form.action || window.location.href, {
        method: 'POST',
        body: new FormData(form)
    })
    .then(function(r) { return r.text(); })
    .then(function(html) {
        var hasSuccess = html.indexOf('alert-success') !== -1 || html.indexOf('succ') !== -1;
        if (hasSuccess) {
            alertDiv.className = 'alert alert-success alert-dismissible fade show';
            alertDiv.innerHTML = '<i class="bi bi-check-circle me-2"></i>Versement enregistré ! Saisissez un nouveau versement ci-dessous.<button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="alert"></button>';
            alertDiv.style.display = 'block';
            // Update CSRF token
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            var newCsrf = doc.querySelector('[name="csrf_token"]');
            if (newCsrf) form.querySelector('[name="csrf_token"]').value = newCsrf.value;
            // Reset fields for new entry
            resetCreateForm();
            // Reload table in background after short delay
            setTimeout(function() { window.location.reload(); }, 3000);
        } else {
            alertDiv.className = 'alert alert-danger alert-dismissible fade show';
            alertDiv.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Erreur lors de l\'enregistrement. Vérifiez les champs obligatoires.<button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="alert"></button>';
            alertDiv.style.display = 'block';
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Enregistrer & Nouveau';
    })
    .catch(function() {
        alertDiv.className = 'alert alert-danger alert-dismissible fade show';
        alertDiv.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Erreur réseau. Réessayez.<button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="alert"></button>';
        alertDiv.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Enregistrer & Nouveau';
    });
});

// Edit modal - populate fields
document.querySelectorAll('.btn-edit-versement').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_id').value = this.dataset.id;
        document.getElementById('edit_dateversement').value = this.dataset.dateversement;
        document.getElementById('edit_sommeverse').value = this.dataset.sommeverse;
        document.getElementById('edit_refversement').value = this.dataset.refversement;
        document.getElementById('edit_agenceverse_id').value = this.dataset.agenceverse_id || '';
        document.getElementById('edit_bank_id').value = this.dataset.bank_id || '';
        document.getElementById('edit_nomoperateur').value = this.dataset.nomoperateur;
        document.getElementById('edit_description').value = this.dataset.description;

        // Auto-fetch recette for this agence+date
        fetchRecetteAgence('edit');

        var justInfo = document.getElementById('edit_justificatif_info');
        if (this.dataset.justificatif) {
            justInfo.textContent = 'Justificatif actuel : ' + this.dataset.justificatif;
        } else {
            justInfo.textContent = '';
        }
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>