<?php
// AJAX endpoint: create / update recette camion (must be BEFORE includes to return clean JSON)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/auth.php';
    startSecureSession();
    $db = getDB();

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'message'=>'Token de sécurité invalide.']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create' && hasPermission('recettes_camions_create')) {
        $date_recette = cleanInput($_POST['date_recette'] ?? '');
        $montant = (float)($_POST['montant'] ?? 0);
        $vehicule_id = (int)($_POST['vehicule_id'] ?? 0) ?: null;
        $agence_depart_id = (int)($_POST['agence_depart_id'] ?? 0) ?: null;
        $agence_arrivee_id = (int)($_POST['agence_arrivee_id'] ?? 0) ?: null;
        $categorie_id = (int)($_POST['categorie_id'] ?? 0) ?: null;
        $trajet = cleanInput($_POST['trajet'] ?? '');
        $client = cleanInput($_POST['client'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');

        if (empty($date_recette) || $montant <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'message'=>'La date et le montant sont obligatoires.']);
            exit;
        }

        $reference = generateReference('RCR');
        try {
            $stmt = $db->prepare("INSERT INTO recettes_camions (reference, date_recette, montant, vehicule_id, agence_id, agence_depart_id, agence_arrivee_id, categorie_id, trajet, client, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$reference, $date_recette, $montant, $vehicule_id, $agence_depart_id, $agence_depart_id, $agence_arrivee_id, $categorie_id, $trajet, $client, $description, $_SESSION['user_id']]);
            $newId = $db->lastInsertId();

            if (isset($_FILES['justificatif']) && $_FILES['justificatif']['error'] === UPLOAD_ERR_OK) {
                $path = uploadJustificatif($_FILES['justificatif'], 'recette_camion', $newId);
                if ($path) $db->prepare("UPDATE recettes_camions SET justificatif_path = ? WHERE id = ?")->execute([$path, $newId]);
            }

            addAuditLog('create', 'recette_camion', $newId, [], ['reference' => $reference, 'montant' => $montant]);
            header('Content-Type: application/json');
            echo json_encode(['success'=>true,'message'=>'Recette camion créée avec succès.','csrf_token'=>generateCSRFToken()]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'message'=>'Erreur : ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update' && hasPermission('recettes_camions_edit')) {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM recettes_camions WHERE id = ?");
        $stmt->execute([$id]);
        $recette = $stmt->fetch();
        if (!$recette || $recette['statut'] !== 'en_attente') {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'message'=>'Recette camion introuvable ou déjà traitée.']);
            exit;
        }

        $date_recette = cleanInput($_POST['date_recette'] ?? '');
        $montant = (float)($_POST['montant'] ?? 0);
        $vehicule_id = (int)($_POST['vehicule_id'] ?? 0) ?: null;
        $agence_depart_id = (int)($_POST['agence_depart_id'] ?? 0) ?: null;
        $agence_arrivee_id = (int)($_POST['agence_arrivee_id'] ?? 0) ?: null;
        $categorie_id = (int)($_POST['categorie_id'] ?? 0) ?: null;
        $trajet = cleanInput($_POST['trajet'] ?? '');
        $client = cleanInput($_POST['client'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');

        if (empty($date_recette) || $montant <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'message'=>'La date et le montant sont obligatoires.']);
            exit;
        }

        try {
            $stmt = $db->prepare("UPDATE recettes_camions SET date_recette=?, montant=?, vehicule_id=?, agence_id=?, agence_depart_id=?, agence_arrivee_id=?, categorie_id=?, trajet=?, client=?, description=? WHERE id=?");
            $stmt->execute([$date_recette, $montant, $vehicule_id, $agence_depart_id, $agence_depart_id, $agence_arrivee_id, $categorie_id, $trajet, $client, $description, $id]);

            if (isset($_FILES['justificatif']) && $_FILES['justificatif']['error'] === UPLOAD_ERR_OK) {
                $path = uploadJustificatif($_FILES['justificatif'], 'recette_camion', $id);
                if ($path) $db->prepare("UPDATE recettes_camions SET justificatif_path = ? WHERE id = ?")->execute([$path, $id]);
            }

            addAuditLog('update', 'recette_camion', $id, $recette, ['montant' => $montant, 'date' => $date_recette]);
            header('Content-Type: application/json');
            echo json_encode(['success'=>true,'message'=>'Recette camion modifiée.']);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'message'=>'Erreur : ' . $e->getMessage()]);
        }
        exit;
    }

    // Unknown AJAX action
    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'message'=>'Action non reconnue.']);
    exit;
}

$pageTitle = 'Recettes Camions';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('recettes_camions');

$db = getDB();
$agences = getAgences($db);
$vehicules = getVehicules($db);
$categories = getCategories($db, 'recette');

// Get location_vehicule category ID for JS conditional
$locationCatStmt = $db->prepare("SELECT id FROM categories WHERE code = 'location_vehicule' AND statut = 'actif'");
$locationCatStmt->execute();
$locationCatId = $locationCatStmt->fetchColumn();

// Filters
$filterDateFrom = $_GET['date_from'] ?? date('Y-m-01');
$filterDateTo = $_GET['date_to'] ?? date('Y-m-t');
$filterAgence = $_GET['agence_id'] ?? '';
$filterVehicule = $_GET['vehicule_id'] ?? '';
$filterStatut = $_GET['statut'] ?? '';
$filterSearch = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$currentPage = $page;

// Actions POST (validate / cancel only — create/update handled by AJAX endpoint above)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'validate' && hasPermission('recettes_camions_validate')) {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE recettes_camions SET statut='validee', validated_by=? WHERE id=? AND statut='en_attente'")->execute([$_SESSION['user_id'], $id]);
        addAuditLog('validate', 'recette_camion', $id);
        addNotification(null, 'success', 'Recette camion validée', 'La recette camion #' . $id . ' a été validée.');
        setFlash('success', 'Recette camion validée avec succès.');
    } elseif ($action === 'cancel' && hasPermission('recettes_camions_validate')) {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE recettes_camions SET statut='annulee', validated_by=? WHERE id=? AND statut='en_attente'")->execute([$_SESSION['user_id'], $id]);
        addAuditLog('cancel', 'recette_camion', $id);
        setFlash('warning', 'Recette camion annulée.');
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
    exit;
}

// Build query
$where = ["r.date_recette BETWEEN ? AND ?"];
$params = [$filterDateFrom, $filterDateTo];

if ($filterAgence) { $where[] = "(r.agence_depart_id = ? OR r.agence_arrivee_id = ?)"; $params[] = $filterAgence; $params[] = $filterAgence; }
if ($filterVehicule) { $where[] = "r.vehicule_id = ?"; $params[] = $filterVehicule; }
if ($filterStatut) { $where[] = "r.statut = ?"; $params[] = $filterStatut; }
if ($filterSearch) { $where[] = "(r.reference LIKE ? OR r.client LIKE ? OR r.trajet LIKE ?)"; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; }

$whereClause = implode(' AND ', $where);
$query = "SELECT r.*, v.immatriculation, v.marque,
          ad.nomagence as agence_depart_nom,
          aa.nomagence as agence_arrivee_nom,
          c.nom as cat_nom
          FROM recettes_camions r
          LEFT JOIN vehicules v ON r.vehicule_id = v.id
          LEFT JOIN agence ad ON r.agence_depart_id = ad.id
          LEFT JOIN agence aa ON r.agence_arrivee_id = aa.id
          LEFT JOIN categories c ON r.categorie_id = c.id
          WHERE $whereClause ORDER BY r.date_recette DESC, r.created_at DESC";

$result = paginate($db, $query, $params, $page);
$totalMontant = array_sum(array_column($result['data'], 'montant'));
?>

<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <h6 class="mb-0 fw-bold"><?php echo e($pageTitle); ?></h6>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <button class="notif-btn" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button>
                <div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div>
            </div>
            <div class="dropdown">
                <div class="header-user" data-bs-toggle="dropdown">
                    <div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div>
                    <div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div>
                </div>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a>
                </div>
            </div>
        </div>
    </header>

    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>

        <div class="page-header">
            <div>
                <h1 class="page-title"><i class="bi bi-truck me-2"></i>Recettes Camions</h1>
                <p class="page-subtitle">Gestion des recettes transport marchandises</p>
            </div>
            <?php if (hasPermission('recettes_camions_create')): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Nouvelle recette</button>
            <?php endif; ?>
        </div>

        <!-- Filters -->
        <div class="card mb-3"><div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2"><label class="form-label">Du</label><input type="date" name="date_from" value="<?php echo e($filterDateFrom); ?>" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Au</label><input type="date" name="date_to" value="<?php echo e($filterDateTo); ?>" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Agence</label><select name="agence_id" class="form-select"><option value="">Toutes</option><?php foreach($agences as $a): ?><option value="<?php echo $a['id']; ?>" <?php echo $filterAgence == $a['id'] ? 'selected' : ''; ?>><?php echo e($a['nomagence']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Camion</label><select name="vehicule_id" class="form-select"><option value="">Tous</option><?php foreach($vehicules as $v): ?><option value="<?php echo $v['id']; ?>" <?php echo $filterVehicule == $v['id'] ? 'selected' : ''; ?>><?php echo e($v['immatriculation']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Statut</label><select name="statut" class="form-select"><option value="">Tous</option><?php foreach(STATUSES as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo $filterStatut === $k ? 'selected' : ''; ?>><?php echo e($l); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-1"><button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i></button></div>
            </form>
        </div></div>

        <!-- Total -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted">Total : <strong class="text-success"><?php echo formatMoney($totalMontant); ?></strong></span>
            <span class="text-muted"><?php echo $result['total']; ?> résultat(s)</span>
        </div>

        <!-- Table -->
        <div class="card"><div class="table-container">
            <table class="table">
                <thead><tr>
                    <th>Réf</th><th>Date</th><th>Catégorie</th><th>Camion</th><th>Agence départ</th><th>Agence arrivée</th><th>Client</th><th>Montant</th><th>Statut</th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($result['data'] as $r): ?>
                    <tr>
                        <td><small class="fw-600"><?php echo e($r['reference']); ?></small></td>
                        <td><?php echo formatDateShort($r['date_recette']); ?></td>
                        <td><small><?php echo e($r['cat_nom'] ?? '-'); ?></small></td>
                        <td><?php echo e($r['immatriculation'] ?? '-'); ?></td>
                        <td><small><?php echo e($r['agence_depart_nom'] ?? '-'); ?></small></td>
                        <td><small><?php echo e($r['agence_arrivee_nom'] ?? '-'); ?></small></td>
                        <td><?php echo e($r['client'] ?? '-'); ?></td>
                        <td class="fw-bold text-success"><?php echo formatMoney($r['montant']); ?></td>
                        <td><?php echo getStatusBadge($r['statut']); ?></td>
                        <td>
                            <div class="action-btns">
                                <a href="view.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary" title="Voir"><i class="bi bi-eye"></i></a>
                                <?php if ($r['statut'] === 'en_attente' && hasPermission('recettes_camions_edit')): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning btn-edit-rc" title="Modifier"
                                    data-id="<?php echo $r['id']; ?>"
                                    data-date="<?php echo e($r['date_recette']); ?>"
                                    data-montant="<?php echo $r['montant']; ?>"
                                    data-vehicule_id="<?php echo $r['vehicule_id'] ?? ''; ?>"
                                    data-agence_depart_id="<?php echo $r['agence_depart_id'] ?? ''; ?>"
                                    data-agence_arrivee_id="<?php echo $r['agence_arrivee_id'] ?? ''; ?>"
                                    data-categorie_id="<?php echo $r['categorie_id'] ?? ''; ?>"
                                    data-trajet="<?php echo e($r['trajet'] ?? ''); ?>"
                                    data-client="<?php echo e($r['client'] ?? ''); ?>"
                                    data-description="<?php echo e($r['description'] ?? ''); ?>"
                                    data-justificatif="<?php echo e($r['justificatif_path'] ?? ''); ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($r['statut'] === 'en_attente' && hasPermission('recettes_camions_validate')): ?>
                                <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="id" value="<?php echo $r['id']; ?>"><input type="hidden" name="action" value="validate"><button type="submit" class="btn btn-sm btn-outline-success" data-confirm="Valider cette recette ?" title="Valider"><i class="bi bi-check-lg"></i></button></form>
                                <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="id" value="<?php echo $r['id']; ?>"><input type="hidden" name="action" value="cancel"><button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Annuler cette recette ?" title="Annuler"><i class="bi bi-x-lg"></i></button></form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($result['data'])): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">Aucune recette camion trouvée</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div></div>

        <?php echo renderPagination($result['page'], $result['totalPages'], '?' . http_build_query(array_filter(['date_from'=>$filterDateFrom,'date_to'=>$filterDateTo,'agence_id'=>$filterAgence,'vehicule_id'=>$filterVehicule,'statut'=>$filterStatut]))); ?>
    </div>
</div>

<!-- Modal Créer Recette Camion -->
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-truck me-2"></i>Nouvelle Recette Camion</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="" enctype="multipart/form-data" data-validate id="createForm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
            <div id="createAlert" class="alert" style="display:none;"></div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                    <input type="date" name="date_recette" id="create_date_recette" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Montant (FCFA) <span class="text-danger">*</span></label>
                    <input type="number" name="montant" id="create_montant" class="form-control" min="0" step="1" required placeholder="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Catégorie <span class="text-danger">*</span></label>
                    <select name="categorie_id" id="create_categorie_id" class="form-select" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo e($c['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Agence de départ <span class="text-danger">*</span></label>
                    <select name="agence_depart_id" id="create_agence_depart_id" class="form-select" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach($agences as $a): ?>
                        <option value="<?php echo $a['id']; ?>"><?php echo e($a['nomagence']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Agence d'arrivée <span class="text-danger">*</span></label>
                    <select name="agence_arrivee_id" id="create_agence_arrivee_id" class="form-select" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach($agences as $a): ?>
                        <option value="<?php echo $a['id']; ?>"><?php echo e($a['nomagence']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Camion</label>
                    <select name="vehicule_id" id="create_vehicule_id" class="form-select">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach($vehicules as $v): ?>
                        <option value="<?php echo $v['id']; ?>"><?php echo e($v['immatriculation'] . ' - ' . $v['marque']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6" id="create_client_group" style="display:none;">
                    <label class="form-label fw-semibold">Client <span class="text-danger">*</span></label>
                    <input type="text" name="client" id="create_client" class="form-control" placeholder="Nom du client">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Justificatif</label>
                    <input type="file" name="justificatif" id="create_justificatif" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                </div>
                <div class="col-12">
                    <label class="form-label">Observation</label>
                    <textarea name="description" id="create_description" class="form-control" rows="2" placeholder="Observation sur la recette..."></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary" id="createSubmitBtn"><i class="bi bi-check-lg me-1"></i>Enregistrer & Nouveau</button>
        </div>
    </form>
</div></div></div>

<!-- Modal Modifier Recette Camion -->
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Modifier Recette Camion</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" enctype="multipart/form-data" data-validate>
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                    <input type="date" name="date_recette" id="edit_date_recette" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Montant (FCFA) <span class="text-danger">*</span></label>
                    <input type="number" name="montant" id="edit_montant" class="form-control" min="0" step="1" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Catégorie <span class="text-danger">*</span></label>
                    <select name="categorie_id" id="edit_categorie_id" class="form-select" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo e($c['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Agence de départ <span class="text-danger">*</span></label>
                    <select name="agence_depart_id" id="edit_agence_depart_id" class="form-select" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach($agences as $a): ?>
                        <option value="<?php echo $a['id']; ?>"><?php echo e($a['nomagence']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Agence d'arrivée <span class="text-danger">*</span></label>
                    <select name="agence_arrivee_id" id="edit_agence_arrivee_id" class="form-select" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach($agences as $a): ?>
                        <option value="<?php echo $a['id']; ?>"><?php echo e($a['nomagence']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Camion</label>
                    <select name="vehicule_id" id="edit_vehicule_id" class="form-select">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach($vehicules as $v): ?>
                        <option value="<?php echo $v['id']; ?>"><?php echo e($v['immatriculation'] . ' - ' . $v['marque']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6" id="edit_client_group">
                    <label class="form-label fw-semibold">Client <span class="text-danger">*</span></label>
                    <input type="text" name="client" id="edit_client" class="form-control" placeholder="Nom du client">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nouveau justificatif</label>
                    <input type="file" name="justificatif" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    <small class="text-muted" id="edit_justificatif_info"></small>
                </div>
                <div class="col-12">
                    <label class="form-label">Observation</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
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

// Category ID for "location_vehicule" — show client field only for this category
var LOCATION_CAT_ID = <?php echo json_encode($locationCatId); ?>;

function toggleClientField(catSelect, clientGroupId) {
    var group = document.getElementById(clientGroupId);
    if (!group) return;
    var isLocation = catSelect.value == LOCATION_CAT_ID;
    group.style.display = isLocation ? '' : 'none';
    var clientInput = group.querySelector('input[name="client"]');
    if (clientInput) {
        clientInput.required = isLocation;
        if (!isLocation) clientInput.value = '';
    }
}

// Create modal — toggle client on category change
document.getElementById('create_categorie_id')?.addEventListener('change', function() {
    toggleClientField(this, 'create_client_group');
});

// Edit modal — toggle client on category change
document.getElementById('edit_categorie_id')?.addEventListener('change', function() {
    toggleClientField(this, 'edit_client_group');
});

// Reset create form for continuous entry
function resetCreateForm() {
    var form = document.getElementById('createForm');
    form.querySelector('[name="montant"]').value = '';
    form.querySelector('[name="categorie_id"]').value = '';
    form.querySelector('[name="agence_depart_id"]').value = '';
    form.querySelector('[name="agence_arrivee_id"]').value = '';
    form.querySelector('[name="vehicule_id"]').value = '';
    form.querySelector('[name="client"]').value = '';
    form.querySelector('[name="description"]').value = '';
    var fileInput = document.getElementById('create_justificatif');
    if (fileInput) fileInput.value = '';
    // Hide client field
    document.getElementById('create_client_group').style.display = 'none';
    document.getElementById('create_client').required = false;
}

// AJAX submit for create form — keep modal open for continuous entry
document.getElementById('createForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    e.stopPropagation();
    var form = this;
    var alertDiv = document.getElementById('createAlert');
    var btn = document.getElementById('createSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enregistrement...';
    alertDiv.style.display = 'none';

    var formData = new FormData(form);
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
            throw new Error('Réponse invalide du serveur');
        }
        return data;
    })
    .then(function(data) {
        if (data.success) {
            alertDiv.className = 'alert alert-success alert-dismissible fade show';
            alertDiv.innerHTML = '<i class="bi bi-check-circle me-2"></i>' + data.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            alertDiv.style.display = 'block';
            if (data.csrf_token) form.querySelector('[name="csrf_token"]').value = data.csrf_token;
            resetCreateForm();
        } else {
            alertDiv.className = 'alert alert-danger alert-dismissible fade show';
            alertDiv.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>' + (data.message || 'Erreur lors de l\'enregistrement.') + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            alertDiv.style.display = 'block';
            if (data.csrf_token) form.querySelector('[name="csrf_token"]').value = data.csrf_token;
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Enregistrer & Nouveau';
    })
    .catch(function(err) {
        console.error('Fetch error:', err);
        alertDiv.className = 'alert alert-danger alert-dismissible fade show';
        alertDiv.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Erreur : ' + (err.message || 'Impossible de contacter le serveur.') + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        alertDiv.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Enregistrer & Nouveau';
    });
});

// AJAX submit for edit form — close modal on success
document.querySelector('#editModal form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    e.stopPropagation();
    var form = this;
    var btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enregistrement...';

    var formData = new FormData(form);
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
            throw new Error('Réponse invalide du serveur');
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

// Edit modal — populate fields
document.querySelectorAll('.btn-edit-rc').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_id').value = this.dataset.id;
        document.getElementById('edit_date_recette').value = this.dataset.date;
        document.getElementById('edit_montant').value = this.dataset.montant;
        document.getElementById('edit_vehicule_id').value = this.dataset.vehicule_id || '';
        document.getElementById('edit_agence_depart_id').value = this.dataset.agence_depart_id || '';
        document.getElementById('edit_agence_arrivee_id').value = this.dataset.agence_arrivee_id || '';
        document.getElementById('edit_categorie_id').value = this.dataset.categorie_id || '';
        document.getElementById('edit_client').value = this.dataset.client;
        document.getElementById('edit_description').value = this.dataset.description;

        // Toggle client field based on category
        toggleClientField(document.getElementById('edit_categorie_id'), 'edit_client_group');

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