<?php
$pageTitle = 'Importation Excel';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('imports');

$db = getDB();
$error = '';
$imported = 0;
$skipped = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $importType = $_POST['import_type'] ?? '';
    $file = $_FILES['import_file'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Veuillez sélectionner un fichier valide.';
    } elseif ($file['type'] !== 'text/csv' && $file['type'] !== 'application/vnd.ms-excel' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
        $error = 'Seuls les fichiers CSV sont acceptés. Pour Excel, convertissez en CSV d\'abord.';
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle) {
            $headers = fgetcsv($handle, 1000, ';'); // Skip header row
            $agenceId = (int)($_POST['agence_id'] ?? 0) ?: null;

            while (($row = fgetcsv($handle, 1000, ';')) !== false) {
                if (count($row) < 3) { $skipped++; continue; }

                try {
                    switch ($importType) {
                        case 'recettes':
                            $stmt = $db->prepare("INSERT INTO recette (reference, date, montantexpedition, montantAccompagnement, agence_id, description, created_by) VALUES (?,?,?,?,?,?,?)");
                            $stmt->execute([
                                generateReference('RJR'),
                                $row[0] ?? date('Y-m-d'),
                                (float)($row[1] ?? 0),
                                0,
                                $agenceId,
                                $row[2] ?? '',
                                $_SESSION['user_id']
                            ]);
                            $imported++;
                            break;

                        case 'depenses':
                            $stmt = $db->prepare("INSERT INTO depenses (reference, date_depense, montant, categorie_id, agence_id, nature, description, created_by) VALUES (?,?,?,?,?,?,?,?)");
                            $stmt->execute([
                                generateReference('DEP'),
                                $row[0] ?? date('Y-m-d'),
                                (float)($row[1] ?? 0),
                                (int)($row[2] ?? 0) ?: null,
                                $agenceId,
                                $row[3] ?? '',
                                $row[4] ?? '',
                                $_SESSION['user_id']
                            ]);
                            $imported++;
                            break;

                        case 'recettes_camions':
                            $agenceDepartId = (int)($row[2] ?? 0) ?: $agenceId;
                            $agenceArriveeId = (int)($row[3] ?? 0) ?: null;
                            $stmt = $db->prepare("INSERT INTO recettes_camions (reference, date_recette, montant, vehicule_id, agence_id, agence_depart_id, agence_arrivee_id, categorie_id, trajet, client, description, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                            $stmt->execute([
                                generateReference('RCR'),
                                $row[0] ?? date('Y-m-d'),
                                (float)($row[1] ?? 0),
                                (int)($row[4] ?? 0) ?: null,
                                $agenceDepartId,
                                $agenceDepartId,
                                $agenceArriveeId,
                                (int)($row[5] ?? 0) ?: null,
                                $row[6] ?? '',
                                $row[7] ?? '',
                                $row[8] ?? '',
                                $_SESSION['user_id']
                            ]);
                            $imported++;
                            break;

                        default:
                            $skipped++;
                    }
                } catch (Exception $e) {
                    $skipped++;
                }
            }
            fclose($handle);
            addAuditLog('import', 'system', 0, [], ['type' => $importType, 'imported' => $imported, 'skipped' => $skipped]);
            setFlash('success', "Import terminé : $imported importé(s), $skipped ignoré(s).");
            header('Location: index.php');
            exit;
        } else {
            $error = 'Impossible de lire le fichier.';
        }
    }
}

$agences = getAgences($db);
?>
<div class="main-content">
    <header class="main-header"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button><h6 class="mb-0 fw-bold"><?php echo e($pageTitle); ?></h6></div><div class="header-right"><div class="dropdown"><button class="notif-btn" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-file-earmark-excel me-2"></i>Importation Excel/CSV</h1><p class="page-subtitle">Importez vos données depuis un fichier CSV</p></div></div>

        <?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?php echo e($error); ?></div><?php endif; ?>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card"><div class="card-header"><i class="bi bi-upload me-2"></i>Importer un fichier</div><div class="card-body">
                    <form method="POST" enctype="multipart/form-data" data-validate>
                        <?php echo csrfField(); ?>
                        <div class="mb-3"><label class="form-label">Type d'import <span class="text-danger">*</span></label>
                            <select name="import_type" class="form-select" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="recettes">Recettes journalières</option>
                                <option value="depenses">Dépenses</option>
                                <option value="recettes_camions">Recettes camions</option>
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">Agence</label>
                            <select name="agence_id" class="form-select">
                                <option value="">-- Aucune --</option>
                                <?php foreach($agences as $a): ?><option value="<?php echo $a['id']; ?>"><?php echo e($a['nomagence']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">Fichier CSV <span class="text-danger">*</span></label>
                            <input type="file" name="import_file" class="form-control" accept=".csv" required>
                            <small class="text-muted">Format CSV avec séparateur point-virgule (;)</small>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Importer</button>
                    </form>
                </div></div>
            </div>
            <div class="col-lg-6">
                <div class="card"><div class="card-header"><i class="bi bi-info-circle me-2"></i>Formats attendus</div><div class="card-body">
                    <h6>Recettes journalières</h6>
                    <p class="text-muted small mb-2">Date;Montant;Description</p>
                    <code class="d-block mb-3 small">2024-01-15;150000;Transport marchandises</code>

                    <h6>Dépenses</h6>
                    <p class="text-muted small mb-2">Date;Montant;ID_Catégorie;Nature;Description</p>
                    <code class="d-block mb-3 small">2024-01-15;50000;6;Carburant;Plein camion</code>

                    <h6>Recettes camions</h6>
                    <p class="text-muted small mb-2">Date;Montant;ID_Camion;Trajet;Client;Description</p>
                    <code class="d-block mb-3 small">2024-01-15;200000;1;Conakry-Kankan;Client ABC;Transport</code>

                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Important :</strong> Les imports sont créés en statut "En attente" et doivent être validés.
                    </div>
                </div></div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>