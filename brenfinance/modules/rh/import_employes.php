<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireModuleAccess('rh');

if (!hasPermission('rh', 'importer')) {
    flash('danger', "Vous n'avez pas la permission d'importer des employés.");
    header('Location: index.php'); exit;
}

$pageTitle = 'Importer des employés';
$db = getDB();

// Download template
if (isset($_GET['download']) && $_GET['download'] === 'template') {
    $file = __DIR__ . '/template_employes.csv';
    if (file_exists($file)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="template_employes.csv"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
    } else {
        flash('danger', 'Template introuvable.');
        header('Location: import_employes.php'); exit;
    }
    exit;
}

// Download error report
if (isset($_GET['download']) && $_GET['download'] === 'errors') {
    $errors = $_SESSION['import_errors_report'] ?? [];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapport_erreurs_import.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($out, ['Ligne', 'Erreur']);
    foreach ($errors as $err) {
        fputcsv($out, [$err['ligne'], $err['erreur']]);
    }
    fclose($out);
    unset($_SESSION['import_errors_report']);
    exit;
}

// POST: process import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fichier_csv'])) {
    $file = $_FILES['fichier_csv'];
    $results = ['total' => 0, 'importes' => 0, 'erreurs' => 0, 'detail_erreurs' => []];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('danger', 'Erreur lors du téléchargement du fichier.');
        header('Location: import_employes.php'); exit;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        flash('danger', 'Le fichier ne doit pas dépasser 5 Mo.');
        header('Location: import_employes.php'); exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        flash('danger', 'Seuls les fichiers CSV sont acceptés.');
        header('Location: import_employes.php'); exit;
    }

    // Auto-detect separator
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        flash('danger', 'Impossible de lire le fichier.');
        header('Location: import_employes.php'); exit;
    }

    $firstLine = fgets($handle);
    $semicolonCount = substr_count($firstLine, ';');
    $commaCount = substr_count($firstLine, ',');
    $separator = $semicolonCount > $commaCount ? ';' : ',';

    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    // Handle BOM
    $content = str_replace("\xEF\xBB\xBF", '', $content);

    // Parse CSV into array
    $lines = str_getcsv_all($content, $separator);

    if (empty($lines)) {
        flash('danger', 'Le fichier est vide.');
        header('Location: import_employes.php'); exit;
    }

    // Header row
    $headers = array_map('trim', $lines[0]);
    $requiredColumns = ['nom', 'prenom', 'email', 'salaire_base', 'date_embauche'];
    $missingColumns = array_diff($requiredColumns, $headers);
    if (!empty($missingColumns)) {
        flash('danger', 'Colonnes obligatoires manquantes : ' . implode(', ', $missingColumns));
        header('Location: import_employes.php'); exit;
    }

    // Preload reference data
    $rolesMap = [];
    foreach ($db->query("SELECT id, nom FROM roles")->fetchAll() as $r) {
        $rolesMap[strtolower($r['nom'])] = $r['id'];
    }

    $agencesMap = [];
    foreach ($db->query("SELECT id, nom FROM agences WHERE statut='actif'")->fetchAll() as $a) {
        $agencesMap[strtolower(trim($a['nom']))] = $a['id'];
    }

    $servicesMap = [];
    foreach ($db->query("SELECT id, nom FROM services WHERE statut='actif'")->fetchAll() as $s) {
        $servicesMap[strtolower(trim($s['nom']))] = $s['id'];
    }

    // Existing emails for uniqueness check (employes table)
    $existingEmails = [];
    foreach ($db->query("SELECT email FROM employes")->fetchAll() as $e) {
        $existingEmails[strtolower($e['email'])] = true;
    }

    $validTypesContrat = ['cdi', 'cdd', 'stage', 'prestataire', 'interim'];
    $fileEmails = []; // Track emails within the file

    // Process rows
    $stmtEmp = $db->prepare("INSERT INTO employes (agence_id, service_id, nom, prenom, matricule, email, telephone, genre, statut) VALUES (?,?,?,?,?,?,?,?,'actif')");
    $stmtContrat = $db->prepare("INSERT INTO contrats_employes (employe_id, salaire_base, date_embauche, type_contrat, matricule_cnps, numero_compte, banque, statut) VALUES (?,?,?,?,?,?,?,'actif')");

    for ($i = 1; $i < count($lines); $i++) {
        $row = $lines[$i];
        if (empty(array_filter($row))) continue; // skip empty rows

        $results['total']++;
        $ligne = $i + 1; // 1-indexed for display
        $rowErrors = [];

        // Map row to associative array
        $data = [];
        foreach ($headers as $col => $headerName) {
            $data[$headerName] = isset($row[$col]) ? trim($row[$col]) : '';
        }

        // Validate required fields
        foreach ($requiredColumns as $col) {
            if (empty($data[$col])) {
                $rowErrors[] = "Le champ '$col' est obligatoire";
            }
        }

        if (!empty($rowErrors)) {
            $results['erreurs']++;
            $results['detail_erreurs'][] = ['ligne' => $ligne, 'erreur' => implode('; ', $rowErrors)];
            continue;
        }

        // Validate email
        $email = strtolower($data['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $rowErrors[] = "Email invalide : " . $data['email'];
        } elseif (isset($existingEmails[$email])) {
            $rowErrors[] = "Email déjà existant : " . $data['email'];
        } elseif (isset($fileEmails[$email])) {
            $rowErrors[] = "Email en doublon dans le fichier : " . $data['email'];
        }

        // Validate role (optional, only needed to create user account)
        $roleId = null;
        $roleInput = strtolower(trim($data['role'] ?? ''));
        if (!empty($roleInput)) {
            if (isset($rolesMap[$roleInput])) {
                $roleId = $rolesMap[$roleInput];
            } else {
                $rowErrors[] = "Rôle introuvable : " . $data['role'];
            }
        }

        // Validate agence
        $agenceId = null;
        if (!empty($data['agence'])) {
            $agenceInput = strtolower(trim($data['agence']));
            if (isset($agencesMap[$agenceInput])) {
                $agenceId = $agencesMap[$agenceInput];
            } else {
                $rowErrors[] = "Agence introuvable : " . $data['agence'];
            }
        }

        // Validate service
        $serviceId = null;
        if (!empty($data['service'])) {
            $serviceInput = strtolower(trim($data['service']));
            if (isset($servicesMap[$serviceInput])) {
                $serviceId = $servicesMap[$serviceInput];
            } else {
                $rowErrors[] = "Service introuvable : " . $data['service'];
            }
        }

        // Validate type_contrat
        $typeContrat = strtolower(trim($data['type_contrat'])) ?: 'cdi';
        if (!in_array($typeContrat, $validTypesContrat)) {
            $rowErrors[] = "Type de contrat invalide : " . $data['type_contrat'] . " (valeurs: " . implode(', ', $validTypesContrat) . ")";
        }

        // Validate salaire_base
        $salaireBase = str_replace([' ', "\xc2\xa0"], '', $data['salaire_base']); // remove spaces and non-breaking spaces
        $salaireBase = str_replace(',', '.', $salaireBase); // handle comma decimal
        if (!is_numeric($salaireBase) || floatval($salaireBase) <= 0) {
            $rowErrors[] = "Salaire de base invalide : " . $data['salaire_base'];
        } else {
            $salaireBase = floatval($salaireBase);
        }

        // Validate date_embauche
        $dateEmbauche = $data['date_embauche'];
        $dateParts = explode('-', $dateEmbauche);
        if (count($dateParts) !== 3 || !checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])) {
            $rowErrors[] = "Date d'embauche invalide (format YYYY-MM-DD) : " . $dateEmbauche;
        }

        if (!empty($rowErrors)) {
            $results['erreurs']++;
            $results['detail_erreurs'][] = ['ligne' => $ligne, 'erreur' => implode('; ', $rowErrors)];
            continue;
        }

        // Insert
        try {
            $db->beginTransaction();

            // Generate matricule if empty
            $matricule = $data['matricule'];
            if (empty($matricule)) {
                $matricule = generateNumero('EMP');
            }

            // Create employee
            $stmtEmp->execute([
                $agenceId, $serviceId,
                $data['nom'], $data['prenom'], $matricule,
                $data['email'], $data['telephone'] ?: null,
                strtolower(trim($data['genre'] ?? 'M')) ?: 'M'
            ]);
            $employeId = $db->lastInsertId();

            // Optionally create user account if role provided
            $utilisateurId = null;
            if ($roleId) {
                $password = bin2hex(random_bytes(6));
                $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                $stmtUser = $db->prepare("INSERT INTO utilisateurs (agence_id, service_id, employe_id, role_id, nom, prenom, matricule, email, telephone, password_hash, statut) VALUES (?,?,?,?,?,?,?,?,?,?,'actif')");
                $stmtUser->execute([$agenceId, $serviceId, $employeId, $roleId, $data['nom'], $data['prenom'], $matricule, $data['email'], $data['telephone'] ?: null, $passwordHash]);
                $utilisateurId = $db->lastInsertId();

                $db->prepare("UPDATE employes SET utilisateur_id = ? WHERE id = ?")->execute([$utilisateurId, $employeId]);
            }

            // Create contract
            $stmtContrat->execute([
                $employeId, $salaireBase, $dateEmbauche, $typeContrat,
                $data['matricule_cnps'] ?: null,
                $data['numero_compte'] ?: null,
                $data['banque'] ?: null
            ]);

            $db->commit();

            // Track email as used
            $existingEmails[$email] = true;
            $fileEmails[$email] = true;

            $results['importes']++;

            auditLog('import_employe', 'rh', 'employes', (int)$employeId, null, [
                'nom' => $data['nom'], 'prenom' => $data['prenom'],
                'email' => $data['email'], 'matricule' => $matricule,
                'type_contrat' => $typeContrat, 'salaire_base' => $salaireBase,
                'compte' => $roleId ? 'oui' : 'non'
            ]);

        } catch (PDOException $e) {
            $db->rollBack();
            $results['erreurs']++;
            $results['detail_erreurs'][] = ['ligne' => $ligne, 'erreur' => 'Erreur base de données : ' . $e->getMessage()];
        }
    }

    // Store results in session for display after PRG redirect
    $_SESSION['import_results'] = $results;
    if (!empty($results['detail_erreurs'])) {
        $_SESSION['import_errors_report'] = $results['detail_erreurs'];
    }

    $msg = "Import terminé : {$results['importes']} employé(s) importé(s)";
    if ($results['erreurs'] > 0) {
        $msg .= ", {$results['erreurs']} erreur(s)";
    }
    flash($results['erreurs'] > 0 ? 'warning' : 'success', $msg);
    header('Location: import_employes.php'); exit;
}

// Retrieve results from session after redirect
$importResults = $_SESSION['import_results'] ?? null;
unset($_SESSION['import_results']);

$importErrors = $_SESSION['import_errors_report'] ?? null;

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <div>
        <h1 style="font-size:22px;font-weight:700;color:var(--text);margin:0">Importer des employés</h1>
        <p style="color:var(--text3);font-size:13px;margin:4px 0 0">Import CSV pour créer comptes utilisateurs et contrats</p>
    </div>
    <a href="index.php" class="btn btn-outline"><i class="ph-bold ph-arrow-left"></i> Retour</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px" class="import-layout">
    <!-- Upload form -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="ph-bold ph-upload-simple"></i> Télécharger le fichier</span>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Fichier CSV</label>
                    <input type="file" name="fichier_csv" accept=".csv" class="form-control" required style="padding:8px">
                </div>
                <p style="color:var(--text3);font-size:12px;margin:8px 0 16px">
                    Format : CSV avec en-tête. Séparateur virgule ou point-virgule. Taille max : 5 Mo.
                </p>
                <div style="display:flex;gap:8px">
                    <button type="submit" class="btn btn-primary"><i class="ph-bold ph-upload-simple"></i> Importer</button>
                    <a href="?download=template" class="btn btn-outline"><i class="ph-bold ph-download-simple"></i> Télécharger le template</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Format info -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="ph-bold ph-info"></i> Colonnes du CSV</span>
        </div>
        <div class="card-body" style="font-size:12.5px">
            <table style="font-size:12px">
                <thead><tr><th>Colonne</th><th>Obligatoire</th><th>Exemple</th></tr></thead>
                <tbody>
                    <tr><td>nom</td><td><span class="badge badge-danger">Oui</span></td><td>DUPONT</td></tr>
                    <tr><td>prenom</td><td><span class="badge badge-danger">Oui</span></td><td>Marie</td></tr>
                    <tr><td>matricule</td><td><span class="badge badge-gray">Non</span></td><td>EMP-2025-001</td></tr>
                    <tr><td>email</td><td><span class="badge badge-danger">Oui</span></td><td>marie@exemple.cm</td></tr>
                    <tr><td>telephone</td><td><span class="badge badge-gray">Non</span></td><td>+237699123456</td></tr>
                    <tr><td>agence</td><td><span class="badge badge-gray">Non</span></td><td>Douala</td></tr>
                    <tr><td>service</td><td><span class="badge badge-gray">Non</span></td><td>Comptabilité</td></tr>
                    <tr><td>role</td><td><span class="badge badge-gray">Non</span></td><td>demandeur</td></tr>
                    <tr><td>salaire_base</td><td><span class="badge badge-danger">Oui</span></td><td>250000</td></tr>
                    <tr><td>date_embauche</td><td><span class="badge badge-danger">Oui</span></td><td>2024-01-15</td></tr>
                    <tr><td>type_contrat</td><td><span class="badge badge-gray">Non</span></td><td>cdi</td></tr>
                    <tr><td>matricule_cnps</td><td><span class="badge badge-gray">Non</span></td><td>CNPS-12345</td></tr>
                    <tr><td>numero_compte</td><td><span class="badge badge-gray">Non</span></td><td>CM21...</td></tr>
                    <tr><td>banque</td><td><span class="badge badge-gray">Non</span></td><td>SGBC</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Import results -->
<?php if ($importResults): ?>
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <span class="card-title"><i class="ph-bold ph-check-circle"></i> Résultats de l'import</span>
    </div>
    <div class="card-body">
        <div class="stats-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:16px">
            <div class="stat-card">
                <div class="stat-label">Total</div>
                <div class="stat-value"><?= $importResults['total'] ?></div>
            </div>
            <div class="stat-card" style="border-top-color:var(--success)">
                <div class="stat-label">Importés</div>
                <div class="stat-value" style="color:var(--success)"><?= $importResults['importes'] ?></div>
            </div>
            <div class="stat-card danger">
                <div class="stat-label">Erreurs</div>
                <div class="stat-value" style="color:var(--danger)"><?= $importResults['erreurs'] ?></div>
            </div>
        </div>

        <?php if (!empty($importResults['detail_erreurs'])): ?>
        <div class="card" style="margin-top:12px">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                <span class="card-title" style="color:var(--danger)">Détail des erreurs</span>
                <a href="?download=errors" class="btn btn-sm btn-outline"><i class="ph-bold ph-download-simple"></i> Exporter les erreurs</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Ligne</th><th>Erreur</th></tr></thead>
                    <tbody>
                        <?php foreach ($importResults['detail_erreurs'] as $err): ?>
                        <tr>
                            <td style="font-weight:600"><?= $err['ligne'] ?></td>
                            <td style="color:var(--danger)"><?= sanitize($err['erreur']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<style>
@media (max-width: 768px) {
    .import-layout { grid-template-columns: 1fr !important; }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>