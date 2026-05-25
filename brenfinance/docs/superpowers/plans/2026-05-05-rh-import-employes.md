# Module RH - Importation des Employes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a dedicated RH module with CSV employee import that inserts records into both `utilisateurs` and `contrats_employes` tables.

**Architecture:** New `modules/rh/` directory following the existing procedural PHP single-file pattern. Three pages: dashboard, employee list, CSV import. No external dependencies -- native `fgetcsv()` for CSV parsing, PRG pattern for POST handling, PDO prepared statements for all DB operations.

**Tech Stack:** PHP 7.4+/8.1+, MySQL/MariaDB, PDO, existing BrenFinance helpers (`functions.php`, `header.php`, `footer.php`)

---

### Task 1: Add RH permissions and sidebar entry

**Files:**
- Modify: `includes/functions.php:52-81` (add `rh` to `canSeeModule()`)
- Modify: `includes/header.php:93-169` (add RH sidebar nav item)
- Modify: `modules/admin/index.php:299-317` (add `rh` to `$permModules`)

- [ ] **Step 1: Add `rh` entry to `canSeeModule()` in `includes/functions.php`**

In the `$actions` array inside `canSeeModule()` (after the `paie` entry), add:

```php
'rh' => ['consulter', 'importer', 'modifier'],
```

- [ ] **Step 2: Add RH sidebar nav item in `includes/header.php`**

After the Paie nav item (after the `<?php endif; ?>` that closes the paie block), add:

```php
<?php if (canSeeModule('rh')): ?>
<?= navItem(BASE_URL.'/modules/rh/index.php', 'ph-users-three', 'Ressources Humaines', 'rh', $currentModule) ?>
<?php endif; ?>
```

- [ ] **Step 3: Add `rh` to `$permModules` in `modules/admin/index.php`**

In the `$permModules` array (after the `paie` entry), add:

```php
'rh' => ['consulter', 'importer', 'modifier'],
```

- [ ] **Step 4: Commit**

```bash
git add includes/functions.php includes/header.php modules/admin/index.php
git commit -m "feat: add RH module permissions and sidebar entry"
```

---

### Task 2: Create the CSV template file

**Files:**
- Create: `modules/rh/template_employes.csv`

- [ ] **Step 1: Create the RH module directory and CSV template**

Create `modules/rh/` directory, then create `modules/rh/template_employes.csv` with this content:

```csv
nom,prenom,matricule,email,telephone,agence,service,role,salaire_base,date_embauche,type_contrat,matricule_cnps,numero_compte,banque
DUPONT,Marie,,marie@exemple.cm,+237699123456,Douala,Comptabilite,demandeur,250000,2024-01-15,cdi,CNPS-12345,CM21,SGBC
```

- [ ] **Step 2: Commit**

```bash
git add modules/rh/template_employes.csv
git commit -m "feat: add CSV template for employee import"
```

---

### Task 3: Create the RH dashboard page

**Files:**
- Create: `modules/rh/index.php`

- [ ] **Step 1: Create `modules/rh/index.php`**

```php
<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireModuleAccess('rh');

$pageTitle = 'Ressources Humaines';
$db = getDB();

// Stats
$employesActifs = $db->query("SELECT COUNT(*) FROM utilisateurs u JOIN contrats_employes ce ON ce.utilisateur_id=u.id WHERE u.statut='actif' AND ce.statut='actif'")->fetchColumn();
$masseSalariale = $db->query("SELECT COALESCE(SUM(ce.salaire_base),0) FROM contrats_employes ce JOIN utilisateurs u ON ce.utilisateur_id=u.id WHERE u.statut='actif' AND ce.statut='actif'")->fetchColumn();

$parContrat = $db->query("
    SELECT ce.type_contrat, COUNT(*) as nb
    FROM contrats_employes ce
    JOIN utilisateurs u ON ce.utilisateur_id=u.id
    WHERE u.statut='actif' AND ce.statut='actif'
    GROUP BY ce.type_contrat
    ORDER BY nb DESC
")->fetchAll();

$parAgence = $db->query("
    SELECT COALESCE(a.nom,'Non affecté') as agence, COUNT(*) as nb
    FROM utilisateurs u
    JOIN contrats_employes ce ON ce.utilisateur_id=u.id
    LEFT JOIN agences a ON u.agence_id=a.id
    WHERE u.statut='actif' AND ce.statut='actif'
    GROUP BY u.agence_id
    ORDER BY nb DESC
")->fetchAll();

$recentEmbauches = $db->query("
    SELECT u.nom, u.prenom, u.matricule, ce.date_embauche, ce.type_contrat, a.nom as agence
    FROM utilisateurs u
    JOIN contrats_employes ce ON ce.utilisateur_id=u.id
    LEFT JOIN agences a ON u.agence_id=a.id
    WHERE u.statut='actif'
    ORDER BY ce.date_embauche DESC
    LIMIT 10
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <div>
        <h1 style="font-size:22px;font-weight:700;color:var(--text);margin:0">Ressources Humaines</h1>
        <p style="color:var(--text3);font-size:13px;margin:4px 0 0">Gestion des employés et contrats</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if (hasPermission('rh', 'importer')): ?>
        <a href="import_employes.php" class="btn btn-primary"><i class="ph-bold ph-upload-simple"></i> Importer</a>
        <?php endif; ?>
        <a href="employes.php" class="btn btn-outline"><i class="ph-bold ph-list"></i> Liste des employés</a>
    </div>
</div>

<div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-label">Employés actifs</div>
        <div class="stat-value"><?= $employesActifs ?></div>
    </div>
    <div class="stat-card info">
        <div class="stat-label">Masse salariale</div>
        <div class="stat-value"><?= formatMontant($masseSalariale) ?></div>
    </div>
</div>

<?php if ($parContrat): ?>
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <span class="card-title">Par type de contrat</span>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php foreach ($parContrat as $pc): ?>
            <span class="badge badge-info" style="font-size:12px;padding:5px 12px">
                <?= strtoupper(sanitize($pc['type_contrat'])) ?> : <?= $pc['nb'] ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($parAgence): ?>
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <span class="card-title">Par agence</span>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php foreach ($parAgence as $pa): ?>
            <span class="badge badge-gray" style="font-size:12px;padding:5px 12px">
                <?= sanitize($pa['agence']) ?> : <?= $pa['nb'] ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($recentEmbauches): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">Dernières embauches</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Matricule</th>
                    <th>Date embauche</th>
                    <th>Contrat</th>
                    <th>Agence</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentEmbauches as $e): ?>
                <tr>
                    <td><?= sanitize($e['prenom'] . ' ' . $e['nom']) ?></td>
                    <td><?= sanitize($e['matricule'] ?? '-') ?></td>
                    <td><?= sanitize($e['date_embauche']) ?></td>
                    <td><span class="badge badge-info"><?= strtoupper(sanitize($e['type_contrat'])) ?></span></td>
                    <td><?= sanitize($e['agence'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Commit**

```bash
git add modules/rh/index.php
git commit -m "feat: add RH dashboard page with KPIs"
```

---

### Task 4: Create the employee list page

**Files:**
- Create: `modules/rh/employes.php`

- [ ] **Step 1: Create `modules/rh/employes.php`**

```php
<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireModuleAccess('rh');

$pageTitle = 'Liste des Employés';
$db = getDB();

$search = $_GET['q'] ?? '';
$filtreAgence = $_GET['agence'] ?? '';
$filtreContrat = $_GET['contrat'] ?? '';

$where = ["u.statut='actif'", "ce.statut='actif'"];
$params = [];

if ($search) {
    $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.matricule LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($filtreAgence) {
    $where[] = "u.agence_id = ?";
    $params[] = $filtreAgence;
}
if ($filtreContrat) {
    $where[] = "ce.type_contrat = ?";
    $params[] = $filtreContrat;
}

$whereSQL = implode(' AND ', $where);

$employes = $db->prepare("
    SELECT u.id, u.nom, u.prenom, u.matricule, u.email, u.telephone, u.statut,
           a.nom as agence, s.nom as service, r.nom as role_nom,
           ce.salaire_base, ce.date_embauche, ce.type_contrat, ce.matricule_cnps, ce.statut as contrat_statut
    FROM utilisateurs u
    JOIN contrats_employes ce ON ce.utilisateur_id = u.id
    LEFT JOIN agences a ON u.agence_id = a.id
    LEFT JOIN services s ON u.service_id = s.id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE $whereSQL
    ORDER BY u.nom, u.prenom
");
$employes->execute($params);
$employes = $employes->fetchAll();

$agences = $db->query("SELECT id, nom FROM agences WHERE statut='actif' ORDER BY nom")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <div>
        <h1 style="font-size:22px;font-weight:700;color:var(--text);margin:0">Liste des Employés</h1>
        <p style="color:var(--text3);font-size:13px;margin:4px 0 0"><?= count($employes) ?> employé(s)</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="index.php" class="btn btn-outline"><i class="ph-bold ph-arrow-left"></i> Retour</a>
    </div>
</div>

<div class="card" style="margin-bottom:20px">
    <div class="card-body">
        <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <div class="form-group" style="margin:0;flex:1;min-width:200px">
                <input type="text" name="q" class="form-control" placeholder="Rechercher nom, matricule, email..." value="<?= sanitize($search) ?>">
            </div>
            <div class="form-group" style="margin:0;min-width:160px">
                <select name="agence" class="form-control">
                    <option value="">Toutes les agences</option>
                    <?php foreach ($agences as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $filtreAgence == $a['id'] ? 'selected' : '' ?>><?= sanitize($a['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;min-width:140px">
                <select name="contrat" class="form-control">
                    <option value="">Tous les contrats</option>
                    <option value="cdi" <?= $filtreContrat === 'cdi' ? 'selected' : '' ?>>CDI</option>
                    <option value="cdd" <?= $filtreContrat === 'cdd' ? 'selected' : '' ?>>CDD</option>
                    <option value="stage" <?= $filtreContrat === 'stage' ? 'selected' : '' ?>>Stage</option>
                    <option value="prestataire" <?= $filtreContrat === 'prestataire' ? 'selected' : '' ?>>Prestataire</option>
                    <option value="interim" <?= $filtreContrat === 'interim' ? 'selected' : '' ?>>Intérim</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="ph-bold ph-magnifying-glass"></i> Filtrer</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom complet</th>
                    <th>Matricule</th>
                    <th>Email</th>
                    <th>Agence</th>
                    <th>Service</th>
                    <th>Contrat</th>
                    <th>Salaire</th>
                    <th>Embauche</th>
                    <th>CNPS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employes)): ?>
                <tr><td colspan="9" style="text-align:center;color:var(--text3);padding:40px">Aucun employé trouvé</td></tr>
                <?php endif; ?>
                <?php foreach ($employes as $e): ?>
                <tr>
                    <td><strong><?= sanitize($e['prenom'] . ' ' . $e['nom']) ?></strong></td>
                    <td><?= sanitize($e['matricule'] ?? '-') ?></td>
                    <td style="font-size:12px"><?= sanitize($e['email']) ?></td>
                    <td><?= sanitize($e['agence'] ?? '-') ?></td>
                    <td><?= sanitize($e['service'] ?? '-') ?></td>
                    <td><span class="badge badge-info"><?= strtoupper(sanitize($e['type_contrat'])) ?></span></td>
                    <td style="white-space:nowrap"><?= formatMontant($e['salaire_base']) ?></td>
                    <td><?= sanitize($e['date_embauche']) ?></td>
                    <td style="font-size:12px"><?= sanitize($e['matricule_cnps'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Commit**

```bash
git add modules/rh/employes.php
git commit -m "feat: add employee list page with search and filters"
```

---

### Task 5: Create the CSV import page

**Files:**
- Create: `modules/rh/import_employes.php`

This is the core feature. The page handles:
1. Template download (GET `?download=template`)
2. Error report download (GET `?download=errors`)
3. CSV upload and processing (POST)
4. Results display

- [ ] **Step 1: Create `modules/rh/import_employes.php`**

```php
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
    $requiredColumns = ['nom', 'prenom', 'email', 'role', 'salaire_base', 'date_embauche'];
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

    // Existing emails for uniqueness check
    $existingEmails = [];
    foreach ($db->query("SELECT email FROM utilisateurs")->fetchAll() as $u) {
        $existingEmails[strtolower($u['email'])] = true;
    }

    $validTypesContrat = ['cdi', 'cdd', 'stage', 'prestataire', 'interim'];
    $fileEmails = []; // Track emails within the file

    // Process rows
    $stmtUser = $db->prepare("INSERT INTO utilisateurs (agence_id, service_id, role_id, nom, prenom, matricule, email, telephone, password_hash, statut) VALUES (?,?,?,?,?,?,?,?,?,'actif')");
    $stmtContrat = $db->prepare("INSERT INTO contrats_employes (utilisateur_id, salaire_base, date_embauche, type_contrat, matricule_cnps, numero_compte, banque, statut) VALUES (?,?,?,?,?,?,?,'actif')");

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

        // Validate role
        $roleId = null;
        $roleInput = strtolower(trim($data['role']));
        if (isset($rolesMap[$roleInput])) {
            $roleId = $rolesMap[$roleInput];
        } else {
            $rowErrors[] = "Rôle introuvable : " . $data['role'];
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

            // Generate password
            $password = bin2hex(random_bytes(6)); // 12 chars
            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            // Generate matricule if empty
            $matricule = $data['matricule'];
            if (empty($matricule)) {
                $matricule = generateNumero('EMP');
            }

            $stmtUser->execute([
                $agenceId, $serviceId, $roleId,
                $data['nom'], $data['prenom'], $matricule,
                $data['email'], $data['telephone'] ?: null,
                $passwordHash
            ]);
            $userId = $db->lastInsertId();

            $stmtContrat->execute([
                $userId, $salaireBase, $dateEmbauche, $typeContrat,
                $data['matricule_cnps'] ?: null,
                $data['numero_compte'] ?: null,
                $data['banque'] ?: null
            ]);

            $db->commit();

            // Track email as used
            $existingEmails[$email] = true;
            $fileEmails[$email] = true;

            $results['importes']++;

            auditLog('import_employe', 'rh', 'utilisateurs', (int)$userId, null, [
                'nom' => $data['nom'], 'prenom' => $data['prenom'],
                'email' => $data['email'], 'matricule' => $matricule,
                'type_contrat' => $typeContrat, 'salaire_base' => $salaireBase
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
                    <tr><td>role</td><td><span class="badge badge-danger">Oui</span></td><td>demandeur</td></tr>
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
```

- [ ] **Step 2: Add `str_getcsv_all()` helper function to `includes/functions.php`**

Add this helper after the `sanitize()` function (after line 373):

```php
function str_getcsv_all(string $content, string $separator = ','): array {
    $lines = [];
    $temp = fopen('php://memory', 'r+');
    fwrite($temp, $content);
    rewind($temp);
    while (($row = fgetcsv($temp, 0, $separator)) !== false) {
        $lines[] = $row;
    }
    fclose($temp);
    return $lines;
}
```

- [ ] **Step 3: Commit**

```bash
git add modules/rh/import_employes.php includes/functions.php
git commit -m "feat: add CSV employee import with validation and error reporting"
```

---

### Task 6: Add RH permissions seed data to the database

**Files:**
- Modify: `sql/schema.sql` (add RH permissions to existing roles)

- [ ] **Step 1: Add `rh` permissions to role seed data in `sql/schema.sql`**

`super_admin` already has `{"all": true}` -- no change needed.

For the `daf` role (line 566), add `"rh":{"consulter":true,"importer":true,"modifier":true}` to the existing permissions JSON. The current `daf` permissions end with `"decharge": {"all": true}}`. Add before the closing `}}`:

```sql
'daf' line becomes:
('daf', 'Directeur Administratif et Financier', '{"engagements": {"valider_daf": true, "consulter": true, "executer": true}, "budget": {"all": true}, "reporting": {"all": true}, "tresorerie": {"consulter": true}, "comptabilite": {"consulter": true}, "audit": {"consulter": true}, "ordre_mission": {"valider_daf": true, "consulter": true}, "operations_caisse": {"consulter": true}, "radnex": {"consulter": true}, "decharge": {"all": true}, "rh": {"consulter": true, "importer": true, "modifier": true}}'),
```

- [ ] **Step 2: Run migration on existing database**

Execute via phpMyAdmin or MySQL CLI:

```sql
-- Add RH permissions to existing super_admin and daf roles
UPDATE roles SET permissions = JSON_SET(permissions, '$.rh', '{"consulter":true,"importer":true,"modifier":true}') WHERE nom IN ('super_admin', 'daf');
```

- [ ] **Step 3: Commit**

```bash
git add sql/schema.sql
git commit -m "feat: add RH permissions to role seed data"
```

---

### Task 7: Final verification

- [ ] **Step 1: Verify sidebar shows RH entry for admin/daf users**

Log in as admin, check that "Ressources Humaines" appears in the sidebar under Operations.

- [ ] **Step 2: Verify RH dashboard loads and shows KPIs**

Navigate to `modules/rh/index.php`, confirm stats cards display correctly.

- [ ] **Step 3: Verify template download works**

On the import page, click "Télécharger le template" and confirm CSV downloads.

- [ ] **Step 4: Test CSV import with valid data**

Create a test CSV with 2-3 employee rows, upload it, confirm:
- Employees appear in `utilisateurs` and `contrats_employes` tables
- Success flash message shows
- Results display correct counts

- [ ] **Step 5: Test CSV import with errors**

Upload a CSV with duplicate emails, missing required fields, invalid role -- confirm:
- Partial import succeeds for valid rows
- Error report shows line numbers and reasons
- Error CSV download works

- [ ] **Step 6: Verify employee list page**

Go to `employes.php`, confirm search and filters work, data displays correctly.