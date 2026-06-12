<?php
$pageTitle = 'Importation Excel';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/SimpleXLSX.php';
requirePermission('imports');

use Shuchkin\SimpleXLSX;

$db = getDB();
$error = '';
$imported = 0;
$skipped = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $importType = $_POST['import_type'] ?? '';
    $file = $_FILES['import_file'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Veuillez sélectionner un fichier valide.';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['csv', 'xls', 'xlsx'];
        if (!in_array($ext, $allowed)) {
            $error = 'Format non supporté. Utilisez un fichier .csv, .xls ou .xlsx.';
        } else {
            $rows = [];
            $agenceId = (int)($_POST['agence_id'] ?? 0) ?: null;

            if ($ext === 'csv') {
                // CSV : même logique qu'avant
                $handle = fopen($file['tmp_name'], 'r');
                if ($handle) {
                    fgetcsv($handle, 1000, ';'); // Skip header
                    while (($row = fgetcsv($handle, 1000, ';')) !== false) {
                        $rows[] = $row;
                    }
                    fclose($handle);
                } else {
                    $error = 'Impossible de lire le fichier CSV.';
                }
            } else {
                // XLSX / XLS via SimpleXLSX (supporte les deux formats)
                if ($xlsx = SimpleXLSX::parse($file['tmp_name'])) {
                    $allRows = $xlsx->rows();
                    // Skip header (first row)
                    $rows = array_slice($allRows, 1);
                } else {
                    $error = SimpleXLSX::parseError() ?: 'Fichier Excel invalide ou corrompu.';
                }
            }

            if (!$error) {
                foreach ($rows as $row) {
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
                addAuditLog('import', 'system', 0, [], ['type' => $importType, 'imported' => $imported, 'skipped' => $skipped, 'format' => $ext]);
                setFlash('success', "Import terminé : $imported importé(s), $skipped ignoré(s).");
                header('Location: index.php');
                exit;
            }
        }
    }
}

$agences = getAgences($db);
?>
<div class="main-content" id="main-content" role="main">
    <header class="main-header" role="banner"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button><span class="mb-0 fw-bold"><?php echo e($pageTitle); ?></span></div><div class="header-right"><div class="dropdown"><button class="notif-btn" aria-label="Notifications" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-file-earmark-excel me-2"></i>Importation Excel/CSV</h1><p class="page-subtitle">Importez vos données depuis un fichier Excel (.xls, .xlsx) ou CSV</p></div></div>

        <?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?php echo e($error); ?></div><?php endif; ?>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card"><div class="card-header"><i class="bi bi-upload me-2"></i>Importer un fichier</div><div class="card-body">
                    <form method="POST" enctype="multipart/form-data" data-validate>
                        <?php echo csrfField(); ?>
                        <div class="mb-3"><label for="import_type" class="form-label">Type d'import <span class="text-danger">*</span></label>
                            <select name="import_type" id="import_type" class="form-select" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="recettes">Recettes journalières</option>
                                <option value="depenses">Dépenses</option>
                                <option value="recettes_camions">Recettes camions</option>
                            </select>
                        </div>
                        <div class="mb-3"><label for="filter_agence_id" class="form-label">Agence</label>
                            <select name="agence_id" id="filter_agence_id" class="form-select">
                                <option value="">-- Aucune --</option>
                                <?php foreach($agences as $a): ?><option value="<?php echo $a['id']; ?>"><?php echo e($a['nomagence']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3"><label for="import_file" class="form-label">Fichier <span class="text-danger">*</span></label>
                            <input type="file" name="import_file" id="import_file" class="form-control" accept=".xls,.xlsx,.csv" required>
                            <small class="text-muted">Formats acceptés : Excel (.xls, .xlsx) et CSV (séparateur ;)</small>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Importer</button>
                    </form>
                </div></div>
            </div>
            <div class="col-lg-6">
                <div class="card"><div class="card-header"><i class="bi bi-info-circle me-2"></i>Formats attendus</div><div class="card-body">
                    <h6>Recettes journalières</h6>
                    <p class="text-muted small mb-2">Date | Montant | Description</p>
                    <code class="d-block mb-3 small">2024-01-15 | 150000 | Transport marchandises</code>

                    <h6>Dépenses</h6>
                    <p class="text-muted small mb-2">Date | Montant | ID_Catégorie | Nature | Description</p>
                    <code class="d-block mb-3 small">2024-01-15 | 50000 | 6 | Carburant | Plein camion</code>

                    <h6>Recettes camions</h6>
                    <p class="text-muted small mb-2">Date | Montant | ID_Camion | Trajet | Client | Description</p>
                    <code class="d-block mb-3 small">2024-01-15 | 200000 | 1 | Conakry-Kankan | Client ABC | Transport</code>

                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Excel & CSV :</strong> La première ligne est ignorée (en-têtes). Les imports sont créés en statut "En attente".
                    </div>
                </div></div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
