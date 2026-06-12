<?php
// AJAX endpoint: create / update recette (must be BEFORE includes to return clean JSON)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/auth.php';
    startSecureSession();
    $db = getDB();

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'message'=>'Token de sÃ©curitÃ© invalide.']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    rateLimit('recettes_' . ($action ?: 'crud'));

    if ($action === 'create' && hasPermission('recettes_create')) {
        $date = $_POST['date'] ?? '';
        $agence_id = (int)($_POST['agence_id'] ?? 0) ?: null;
        $montantAccompagnement = (float)str_replace([' ', "\u{00A0}", "\u{202F}"], '', $_POST['montantAccompagnement'] ?? '0');
        $montantexpedition = (float)str_replace([' ', "\u{00A0}", "\u{202F}"], '', $_POST['montantexpedition'] ?? '0');
        $libelle = cleanInput($_POST['libelle'] ?? '');
        $nomoperateur = $_SESSION['full_name'] ?? '';

        if (empty($date) || !$agence_id) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'message'=>'La date et l\'agence sont obligatoires.']);
            exit;
        }
        if (($montantexpedition + $montantAccompagnement) <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'message'=>'Au moins un montant est obligatoire.']);
            exit;
        }

        $reference = generateReference('RJR');
        try {
            $stmt = $db->prepare("INSERT INTO recette (reference, date, montantexpedition, montantAccompagnement, agence_id, nomoperateur, description, created_by) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$reference, $date, $montantexpedition, $montantAccompagnement, $agence_id, $nomoperateur, $libelle, $_SESSION['user_id']]);
            $newId = $db->lastInsertId();

            if (isset($_FILES['justificatif']) && $_FILES['justificatif']['error'] === UPLOAD_ERR_OK) {
                $path = uploadJustificatif($_FILES['justificatif'], 'recette', $newId);
                if ($path) $db->prepare("UPDATE recette SET justificatif_path=? WHERE id=?")->execute([$path, $newId]);
            }

            addAuditLog('create', 'recette', $newId, [], ['reference'=>$reference,'montantexpedition'=>$montantexpedition,'montantAccompagnement'=>$montantAccompagnement]);
            header('Content-Type: application/json');
            echo json_encode(['success'=>true,'message'=>'Recette crÃ©Ã©e avec succÃ¨s.','csrf_token'=>generateCSRFToken()]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'message'=>'Erreur : ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update' && hasPermission('recettes_edit')) {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM recette WHERE id = ?");
        $stmt->execute([$id]);
        $recette = $stmt->fetch();
        if (!$recette || $recette['statut'] !== 'en_attente') {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'message'=>'Recette introuvable ou dÃ©jÃ  traitÃ©e.']);
            exit;
        }

        $date = $_POST['date'] ?? '';
        $montantexpedition = (float)str_replace([' ', "\u{00A0}", "\u{202F}"], '', $_POST['montantexpedition'] ?? '0');
        $montantAccompagnement = (float)str_replace([' ', "\u{00A0}", "\u{202F}"], '', $_POST['montantAccompagnement'] ?? '0');
        $agence_id = (int)($_POST['agence_id'] ?? 0) ?: null;
        $nomediteur = $_SESSION['full_name'] ?? '';
        $dateedite = date('Y-m-d');
        $numerorecu = cleanInput($_POST['numerorecu'] ?? '');
        $destination = cleanInput($_POST['destination'] ?? '');
        $guichetier = cleanInput($_POST['guichetier'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');

        if (empty($date) || ($montantexpedition + $montantAccompagnement) <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'message'=>'La date et au moins un montant sont obligatoires.']);
            exit;
        }

        try {
            $stmt = $db->prepare("UPDATE recette SET date=?, montantexpedition=?, montantAccompagnement=?, agence_id=?, nomediteur=?, dateedite=?, numerorecu=?, destination=?, guichetier=?, description=? WHERE id=?");
            $stmt->execute([$date, $montantexpedition, $montantAccompagnement, $agence_id, $nomediteur, $dateedite, $numerorecu, $destination, $guichetier, $description, $id]);

            if (isset($_FILES['justificatif']) && $_FILES['justificatif']['error'] === UPLOAD_ERR_OK) {
                $path = uploadJustificatif($_FILES['justificatif'], 'recette', $id);
                if ($path) $db->prepare("UPDATE recette SET justificatif_path=? WHERE id=?")->execute([$path, $id]);
            }

            addAuditLog('update', 'recette', $id, $recette, ['date'=>$date,'montantexpedition'=>$montantexpedition,'montantAccompagnement'=>$montantAccompagnement]);
            header('Content-Type: application/json');
            echo json_encode(['success'=>true,'message'=>'Recette modifiÃ©e avec succÃ¨s.']);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'message'=>'Erreur : ' . $e->getMessage()]);
        }
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'message'=>'Action non reconnue.']);
    exit;
}

$pageTitle = 'Recettes JournaliÃ¨res';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('recettes');

$db = getDB();
$filterDateFrom = $_GET['date_from'] ?? date('Y-m-01');
$filterDateTo = $_GET['date_to'] ?? date('Y-m-t');
$filterAgence = $_GET['agence_id'] ?? '';
$filterStatut = $_GET['statut'] ?? '';
$filterSearch = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

// Actions validate / cancel (normal form submit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'validate' && hasPermission('recettes_validate')) {
        $db->prepare("UPDATE recette SET statut='validee', validated_by=? WHERE id=? AND statut='en_attente'")->execute([$_SESSION['user_id'], $id]);
        addAuditLog('validate', 'recette', $id);
        setFlash('success', 'Recette validÃ©e avec succÃ¨s.');
    } elseif ($action === 'cancel' && hasPermission('recettes_validate')) {
        $db->prepare("UPDATE recette SET statut='annulee', validated_by=? WHERE id=? AND statut='en_attente'")->execute([$_SESSION['user_id'], $id]);
        addAuditLog('cancel', 'recette', $id);
        setFlash('warning', 'Recette annulÃ©e.');
    } elseif ($action === 'delete' && hasPermission('recettes_delete')) {
        $stmt = $db->prepare("SELECT * FROM recette WHERE id = ?");
        $stmt->execute([$id]);
        $oldRecord = $stmt->fetch();
        if ($oldRecord) {
            if (!empty($oldRecord['justificatif_path'])) {
                $filePath = UPLOAD_DIR . $oldRecord['justificatif_path'];
                if (file_exists($filePath)) @unlink($filePath);
            }
            $db->prepare("DELETE FROM recette WHERE id = ?")->execute([$id]);
            addAuditLog('delete', 'recette', $id, [], $oldRecord);
            setFlash('success', 'Recette supprimÃ©e.');
        } else {
            setFlash('error', 'Recette introuvable.');
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
    exit;
}

$where = ["r.date BETWEEN ? AND ?"];
$params = [$filterDateFrom, $filterDateTo];
if ($filterAgence) { $where[] = "r.agence_id = ?"; $params[] = $filterAgence; }
if ($filterStatut) { $where[] = "r.statut = ?"; $params[] = $filterStatut; }
if ($filterSearch) { $where[] = "(r.reference LIKE ? OR r.description LIKE ? OR r.destination LIKE ? OR r.nomoperateur LIKE ?)"; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; }

$whereClause = implode(' AND ', $where);
$query = "SELECT r.*, a.nomagence FROM recette r LEFT JOIN agence a ON r.agence_id=a.id WHERE $whereClause ORDER BY r.date DESC, r.created_at DESC";
$result = paginate($db, $query, $params, $page);
$agences = getAgences($db);
$totalMontant = array_sum(array_map(function($r) { return ($r['montantexpedition'] ?? 0) + ($r['montantAccompagnement'] ?? 0); }, $result['data']));
?>
<div class="main-content" id="main-content" role="main">
    <header class="main-header" role="banner">
        <div class="header-left"><button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button><span class="mb-0 fw-bold"><?php echo e($pageTitle); ?></span></div>
        <div class="header-right"><div class="dropdown"><button class="notif-btn" aria-label="Notifications" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" role="button" tabindex="0" aria-label="Menu utilisateur" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>DÃ©connexion</a></div></div></div>
    </header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header">
            <div><h1 class="page-title"><i class="bi bi-cash-coin me-2"></i>Recettes JournaliÃ¨res</h1><p class="page-subtitle">Gestion des recettes journaliÃ¨res</p></div>
            <?php if (hasPermission('recettes_create')): ?><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Nouvelle recette</button><?php endif; ?>
        </div>
        <div class="card mb-3"><div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2"><label for="date_from" class="form-label">Du</label><input type="date" name="date_from" id="date_from" value="<?php echo e($filterDateFrom); ?>" class="form-control"></div>
                <div class="col-md-2"><label for="date_to" class="form-label">Au</label><input type="date" name="date_to" id="date_to" value="<?php echo e($filterDateTo); ?>" class="form-control"></div>
                <div class="col-md-2"><label for="filter_agence_id" class="form-label">Agence</label><select name="agence_id" id="filter_agence_id" class="form-select"><option value="">Toutes</option><?php foreach($agences as $a): ?><option value="<?php echo $a['id']; ?>" <?php echo $filterAgence==$a['id']?'selected':''; ?>><?php echo e($a['nomagence']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label for="filter_statut" class="form-label">Statut</label><select name="statut" id="filter_statut" class="form-select"><option value="">Tous</option><?php foreach(STATUSES as $k=>$l): ?><option value="<?php echo $k; ?>" <?php echo $filterStatut===$k?'selected':''; ?>><?php echo e($l); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-1"><button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i></button></div>
            </form>
        </div></div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted">Total : <strong class="text-success"><?php echo formatMoney($totalMontant); ?></strong></span>
            <span class="text-muted"><?php echo $result['total']; ?> rÃ©sultat(s)</span>
        </div>
        <div class="card"><div class="table-container">
            <table class="table" aria-label="Liste des recettes">
                <thead><tr><th>RÃ©f</th><th>Date</th><th>Agence</th><th>ExpÃ©dition</th><th>AccompagnÃ©</th><th>Total</th><th>Observation</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($result['data'] as $r): ?>
                <tr>
                    <td><small class="fw-600"><?php echo e($r['reference']); ?></small></td>
                    <td><?php echo formatDateShort($r['date']); ?></td>
                    <td><small><?php echo e($r['nomagence'] ?? '-'); ?></small></td>
                    <td class="text-success"><?php echo formatMoney($r['montantexpedition'] ?? 0); ?></td>
                    <td class="text-info"><?php echo formatMoney($r['montantAccompagnement'] ?? 0); ?></td>
                    <td class="fw-bold"><?php echo formatMoney(($r['montantexpedition'] ?? 0) + ($r['montantAccompagnement'] ?? 0)); ?></td>
                    <td><small><?php echo e($r['description'] ?? '-'); ?></small></td>
                    <td><?php echo getStatusBadge($r['statut']); ?></td>
                    <td><div class="action-btns">
                        <a href="view.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary" title="Voir"><i class="bi bi-eye"></i></a>
                        <?php if ($r['statut']==='en_attente' && hasPermission('recettes_edit')): ?>
                        <button type="button" class="btn btn-sm btn-outline-warning btn-edit-recette" title="Modifier"
                            data-id="<?php echo $r['id']; ?>"
                            data-date="<?php echo e($r['date']); ?>"
                            data-agence_id="<?php echo $r['agence_id']; ?>"
                            data-montantexpedition="<?php echo $r['montantexpedition']; ?>"
                            data-montantaccompagnement="<?php echo $r['montantAccompagnement']; ?>"
                            data-nomoperateur="<?php echo e($r['nomoperateur'] ?? ''); ?>"
                            data-numerorecu="<?php echo e($r['numerorecu'] ?? ''); ?>"
                            data-destination="<?php echo e($r['destination'] ?? ''); ?>"
                            data-guichetier="<?php echo e($r['guichetier'] ?? ''); ?>"
                            data-description="<?php echo e($r['description'] ?? ''); ?>"
                            data-justificatif="<?php echo e($r['justificatif_path'] ?? ''); ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php endif; ?>
                        <?php if ($r['statut']==='en_attente' && hasPermission('recettes_validate')): ?>
                        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="id" value="<?php echo $r['id']; ?>"><input type="hidden" name="action" value="validate"><button type="submit" class="btn btn-sm btn-outline-success" aria-label="Valider" data-confirm="Valider ?"><i class="bi bi-check-lg"></i></button></form>
                        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="id" value="<?php echo $r['id']; ?>"><input type="hidden" name="action" value="cancel"><button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Supprimer" data-confirm="Annuler ?"><i class="bi bi-x-lg"></i></button></form>
                        <?php endif; ?>
                        <?php if (hasPermission('recettes_delete')): ?>
                        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="id" value="<?php echo $r['id']; ?>"><input type="hidden" name="action" value="delete"><button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Supprimer" data-confirm="Supprimer cette recette ?" title="Supprimer"><i class="bi bi-trash"></i></button></form>
                        <?php endif; ?>
                    </div></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($result['data'])): ?><tr><td colspan="9" class="text-center text-muted py-4">Aucune recette trouvÃ©e</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
        <?php echo renderPagination($result['page'], $result['totalPages'], '?' . http_build_query(array_filter(['date_from'=>$filterDateFrom,'date_to'=>$filterDateTo,'agence_id'=>$filterAgence,'statut'=>$filterStatut]))); ?>
    </div>
</div>

<!-- Modal Nouvelle Recette -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="addModalLabel"><i class="bi bi-cash-coin me-2"></i>Nouvelle Recette JournaliÃ¨re</h5><button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="" enctype="multipart/form-data" data-validate id="createForm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
            <div id="createAlert" class="alert" style="display:none;"></div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Date recette <span class="text-danger">*</span></label>
                    <input type="date" name="date" id="create_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Agence <span class="text-danger">*</span></label>
                    <select name="agence_id" id="create_agence_id" class="form-select" required>
                        <option value="">-- SÃ©lectionner l'agence --</option>
                        <?php foreach($agences as $a): ?>
                        <option value="<?php echo $a['id']; ?>"><?php echo e($a['nomagence']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Montant expÃ©dition (FCFA) <span class="text-danger">*</span></label>
                    <input type="text" inputmode="numeric" class="form-control amount-input" name="montantexpedition" id="create_montantexpedition" min="0" step="1" placeholder="0" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Montant accompagnÃ©s (FCFA)</label>
                    <input type="text" inputmode="numeric" class="form-control amount-input" name="montantAccompagnement" id="create_montantAccompagnement" min="0" step="1" value="0" placeholder="0">
                </div>
                <div class="col-md-6" style="display:none;">
                    <label for="create_justificatif" class="form-label">OpÃ©rateur de saisie</label>
                    <input type="text" name="nomoperateur" class="form-control" value="<?php echo e($_SESSION['full_name'] ?? ''); ?>" readonly style="background-color:var(--surface-alt);">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Justificatif</label><input type="file" name="justificatif" id="create_justificatif" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Observation</label>
                    <input type="text" name="libelle" id="create_libelle" class="form-control" placeholder="Observation sur la recette...">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary" id="createSubmitBtn"><i class="bi bi-check-lg me-1"></i>Enregistrer & Nouveau</button>
        </div>
    </form>
</div></div></div>

<!-- Modal Modifier Recette -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="editModalLabel"><i class="bi bi-pencil me-2"></i>Modifier Recette</h5><button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="" enctype="multipart/form-data" data-validate id="editForm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                    <input type="date" name="date" id="edit_date" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Montant ExpÃ©dition (FCFA) <span class="text-danger">*</span></label>
                    <input type="text" inputmode="numeric" class="form-control amount-input" name="montantexpedition" id="edit_montantexpedition" min="0" step="1" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Montant Accompagnement (FCFA)</label>
                    <input type="text" inputmode="numeric" class="form-control amount-input" name="montantAccompagnement" id="edit_montantaccompagnement" min="0" step="1">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Agence <span class="text-danger">*</span></label>
                    <select name="agence_id" id="edit_agence_id" class="form-select" required>
                        <option value="">-- SÃ©lectionner --</option>
                        <?php foreach($agences as $a): ?>
                        <option value="<?php echo $a['id']; ?>"><?php echo e($a['nomagence']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="edit_numerorecu" class="form-label">NumÃ©ro de reÃ§u</label><input type="text" name="numerorecu" id="edit_numerorecu" class="form-control">
                </div>
                <div class="col-md-6" style="display:none;">
                    <label for="edit_nomoperateur" class="form-label">OpÃ©rateur de saisie</label><input type="text" id="edit_nomoperateur" class="form-control" readonly style="background-color:var(--surface-alt);">
                </div>
                <div class="col-md-6">
                    <label for="edit_destination" class="form-label">Ã‰diteur</label>
                    <input type="text" class="form-control" value="<?php echo e($_SESSION['full_name'] ?? ''); ?>" readonly style="background-color:var(--surface-alt);">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date Ã©dition</label>
                    <input type="text" class="form-control" value="<?php echo date('d/m/Y'); ?>" readonly style="background-color:var(--surface-alt);">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Destination</label><input type="text" name="destination" id="edit_destination" class="form-control">
                </div>
                <div class="col-md-4">
                    <label for="edit_guichetier" class="form-label">Guichetier</label><input type="text" name="guichetier" id="edit_guichetier" class="form-control">
                </div>
                <div class="col-md-6">
                    <label for="edit_description" class="form-label">Nouveau justificatif</label>
                    <input type="file" name="justificatif" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    <small id="edit_justificatif_info" class="text-muted"></small>
                </div>
                <div class="col-12">
                    <label for="edit_description" class="form-label fw-semibold">Observation</label><textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
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
// Reload page when create modal is closed
document.getElementById('addModal')?.addEventListener('hidden.bs.modal', function() {
    window.location.reload();
});

// Reset create form for continuous entry
function resetCreateForm() {
    var form = document.getElementById('createForm');
    form.querySelector('[name="montantexpedition"]').value = '';
    form.querySelector('[name="montantAccompagnement"]').value = '0';
    form.querySelector('[name="agence_id"]').value = '';
    form.querySelector('[name="libelle"]').value = '';
    var fileInput = document.getElementById('create_justificatif');
    if (fileInput) fileInput.value = '';
}

// AJAX submit for create form
document.getElementById('createForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    e.stopPropagation();
    var form = this;
    var alertDiv = document.getElementById('createAlert');
    var btn = document.getElementById('createSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enregistrement...';
    alertDiv.style.display = 'none';

    // Nettoyer les espaces (séparateurs milliers) avant envoi AJAX
    var ALL_SPACES_RE = /[\s  -   　]/g;
    form.querySelectorAll('[data-amount-formatted]').forEach(function(inp) { inp.value = inp.value.replace(ALL_SPACES_RE, ''); });
    var formData = new FormData(form);
    // Re-formater après capture FormData
    form.querySelectorAll('[data-amount-formatted]').forEach(function(inp) { if (inp.value && !isNaN(inp.value)) { inp.value = Number(inp.value).toLocaleString('fr-FR').replace(/ /g, ' '); } });
    fetch(window.location.pathname, {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(function(r) {
        if (!r.ok) throw new Error('Erreur serveur (' + r.status + ')');
        return r.text();
    })
    .then(function(text) {
        var data;
        try { data = JSON.parse(text); } catch(e) {
            console.error('Non-JSON response:', text.substring(0, 500));
            throw new Error('RÃ©ponse invalide du serveur');
        }
        return data;
    })
    .then(function(data) {
        if (data.success) {
            alertDiv.className = 'alert alert-success alert-dismissible fade show';
            alertDiv.innerHTML = '<i class="bi bi-check-circle me-2"></i>' + data.message + '<button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="alert"></button>';
            alertDiv.style.display = 'block';
            if (data.csrf_token) form.querySelector('[name="csrf_token"]').value = data.csrf_token;
            resetCreateForm();
        } else {
            alertDiv.className = 'alert alert-danger alert-dismissible fade show';
            alertDiv.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>' + (data.message || 'Erreur lors de l\'enregistrement.') + '<button type="="btn-close" data-bs-dismiss="alert"></button>';
            alertDiv.style.display = 'block';
            if (data.csrf_token) form.querySelector('[name="csrf_token"]').value = data.csrf_token;
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Enregistrer & Nouveau';
    })
    .catch(function(err) {
        console.error('Fetch error:', err);
        alertDiv.className = 'alert alert-danger alert-dismissible fade show';
        alertDiv.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Erreur : ' + (err.message || 'Impossible de contacter le serveur.') + '<button type="button" class="btn-close" aria-label="Fermer" data-bs-dismiss="alert"></button>';
        alertDiv.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Enregistrer & Nouveau';
    });
});

// AJAX submit for edit form â€” close modal on success
document.getElementById('editForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    e.stopPropagation();
    var form = this;
    var btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enregistrement...';

    // Nettoyer les espaces (séparateurs milliers) avant envoi AJAX
    var ALL_SPACES_RE = /[\s  -   　]/g;
    form.querySelectorAll('[data-amount-formatted]').forEach(function(inp) { inp.value = inp.value.replace(ALL_SPACES_RE, ''); });
    var formData = new FormData(form);
    form.querySelectorAll('[data-amount-formatted]').forEach(function(inp) { if (inp.value && !isNaN(inp.value)) { inp.value = Number(inp.value).toLocaleString('fr-FR').replace(/ /g, ' '); } });
    fetch(window.location.pathname, {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(function(r) {
        if (!r.ok) throw new Error('Erreur serveur (' + r.status + ')');
        return r.text();
    })
    .then(function(text) {
        var data;
        try { data = JSON.parse(text); } catch(e) {
            console.error('Non-JSON response:', text.substring(0, 500));
            throw new Error('RÃ©ponse invalide du serveur');
        }
        return data;
    })
    .then(function(data) {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
            window.location.reload();
        } else {
            alert(data.message || 'Erreur lors de la modification.');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Enregistrer';
    })
    .catch(function(err) {
        console.error('Fetch error:', err);
        alert('Erreur : ' + (err.message || 'Impossible de contacter le serveur.'));
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Enregistrer';
    });
});

// Edit modal â€” populate fields
document.querySelectorAll('.btn-edit-recette').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_id').value = this.dataset.id;
        document.getElementById('edit_date').value = this.dataset.date;
        document.getElementById('edit_montantexpedition').value = this.dataset.montantexpedition;
        document.getElementById('edit_montantaccompagnement').value = this.dataset.montantaccompagnement;
        document.getElementById('edit_agence_id').value = this.dataset.agence_id;
        document.getElementById('edit_nomoperateur').value = this.dataset.nomoperateur;
        document.getElementById('edit_numerorecu').value = this.dataset.numerorecu;
        document.getElementById('edit_destination').value = this.dataset.destination;
        document.getElementById('edit_guichetier').value = this.dataset.guichetier;
        document.getElementById('edit_description').value = this.dataset.description;
        if (this.dataset.justificatif) {
            document.getElementById('edit_justificatif_info').textContent = 'Actuel : ' + this.dataset.justificatif;
        } else {
            document.getElementById('edit_justificatif_info').textContent = '';
        }
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
