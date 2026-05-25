<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireModuleAccess('rh');

$pageTitle = 'Liste des Employés';
$db = getDB();

$agences = $db->query("SELECT id, nom FROM agences WHERE statut='actif' ORDER BY nom")->fetchAll();
$services = $db->query("SELECT id, nom, agence_id FROM services WHERE statut='actif' ORDER BY nom")->fetchAll();
$roles = $db->query("SELECT id, nom FROM roles ORDER BY nom")->fetchAll();

// ── POST: CRUD actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Créer employé ──
    if ($action === 'create_employe' && hasPermission('rh', 'modifier')) {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $matricule = trim($_POST['matricule'] ?? '');
        $agenceId = (int)($_POST['agence_id'] ?? 0) ?: null;
        $serviceId = (int)($_POST['service_id'] ?? 0) ?: null;
        $genre = trim($_POST['genre'] ?? 'M');
        $dateNaissance = trim($_POST['date_naissance'] ?? '') ?: null;
        $situationFamiliale = trim($_POST['situation_familiale'] ?? 'celibataire');
        $nombreEnfants = (int)($_POST['nombre_enfants'] ?? 0);
        $creerCompte = isset($_POST['creer_compte']);
        $roleId = (int)($_POST['role_id'] ?? 0);
        $salaireBase = str_replace([' ', "\xc2\xa0"], '', $_POST['salaire_base'] ?? '0');
        $salaireBase = str_replace(',', '.', $salaireBase);
        $dateEmbauche = trim($_POST['date_embauche'] ?? '');
        $typeContrat = trim($_POST['type_contrat'] ?? 'cdi');
        $matriculeCnps = trim($_POST['matricule_cnps'] ?? '');
        $numeroCompte = trim($_POST['numero_compte'] ?? '');
        $banque = trim($_POST['banque'] ?? '');

        $errors = [];
        if (empty($nom)) $errors[] = 'Le nom est obligatoire';
        if (empty($prenom)) $errors[] = 'Le prénom est obligatoire';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
        if (empty($salaireBase) || !is_numeric($salaireBase) || floatval($salaireBase) <= 0) $errors[] = 'Salaire de base invalide';
        if (empty($dateEmbauche)) $errors[] = "La date d'embauche est obligatoire";
        if ($creerCompte && empty($roleId)) $errors[] = 'Le rôle est obligatoire pour créer un compte';

        // Check email uniqueness in employes
        if (empty($errors)) {
            $exists = $db->prepare("SELECT id FROM employes WHERE email = ?");
            $exists->execute([$email]);
            if ($exists->fetch()) $errors[] = 'Cet email existe déjà (employé)';
        }
        // Check email in utilisateurs if creating account
        if ($creerCompte && empty($errors)) {
            $existsU = $db->prepare("SELECT id FROM utilisateurs WHERE email = ?");
            $existsU->execute([$email]);
            if ($existsU->fetch()) $errors[] = 'Cet email existe déjà (utilisateur)';
        }

        if (empty($matricule)) {
            $matricule = generateNumero('EMP');
        } else {
            $existsMat = $db->prepare("SELECT id FROM employes WHERE matricule = ?");
            $existsMat->execute([$matricule]);
            if ($existsMat->fetch()) $errors[] = 'Ce matricule existe déjà';
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // Create employee
                $stmtEmp = $db->prepare("INSERT INTO employes (agence_id, service_id, nom, prenom, matricule, email, telephone, genre, date_naissance, situation_familiale, nombre_enfants, statut) VALUES (?,?,?,?,?,?,?,?,?,?,?,'actif')");
                $stmtEmp->execute([$agenceId, $serviceId, $nom, $prenom, $matricule, $email, $telephone ?: null, $genre, $dateNaissance, $situationFamiliale, $nombreEnfants]);
                $employeId = $db->lastInsertId();

                // Optionally create user account
                $utilisateurId = null;
                if ($creerCompte) {
                    $password = bin2hex(random_bytes(6));
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                    $stmtUser = $db->prepare("INSERT INTO utilisateurs (agence_id, service_id, employe_id, role_id, nom, prenom, matricule, email, telephone, password_hash, statut) VALUES (?,?,?,?,?,?,?,?,?,?,'actif')");
                    $stmtUser->execute([$agenceId, $serviceId, $employeId, $roleId, $nom, $prenom, $matricule, $email, $telephone ?: null, $passwordHash]);
                    $utilisateurId = $db->lastInsertId();

                    // Link back
                    $db->prepare("UPDATE employes SET utilisateur_id = ? WHERE id = ?")->execute([$utilisateurId, $employeId]);
                }

                // Create contract
                $stmtContrat = $db->prepare("INSERT INTO contrats_employes (employe_id, utilisateur_id, salaire_base, date_embauche, type_contrat, matricule_cnps, numero_compte, banque, statut) VALUES (?,?,?,?,?,?,?,?,'actif')");
                $stmtContrat->execute([$employeId, $utilisateurId, floatval($salaireBase), $dateEmbauche, $typeContrat, $matriculeCnps ?: null, $numeroCompte ?: null, $banque ?: null]);

                $db->commit();
                auditLog('creation_employe', 'rh', 'employes', (int)$employeId, null, ['nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'matricule' => $matricule, 'compte' => $creerCompte ? 'oui' : 'non']);
                flash('success', "Employé $prenom $nom créé avec succès." . ($creerCompte ? '' : ' (sans compte utilisateur)'));
                header('Location: employes.php'); exit;
            } catch (PDOException $e) {
                $db->rollBack();
                flash('danger', 'Erreur : ' . $e->getMessage());
                header('Location: employes.php'); exit;
            }
        } else {
            flash('danger', implode(' | ', $errors));
            header('Location: employes.php'); exit;
        }
    }

    // ── Modifier employé ──
    if ($action === 'update_employe' && hasPermission('rh', 'modifier')) {
        $employeId = (int)($_POST['employe_id'] ?? 0);
        $contratId = (int)($_POST['contrat_id'] ?? 0);

        if ($employeId <= 0) {
            flash('danger', 'Employé introuvable.');
            header('Location: employes.php'); exit;
        }

        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $matricule = trim($_POST['matricule'] ?? '');
        $agenceId = (int)($_POST['agence_id'] ?? 0) ?: null;
        $serviceId = (int)($_POST['service_id'] ?? 0) ?: null;
        $genre = trim($_POST['genre'] ?? 'M');
        $dateNaissance = trim($_POST['date_naissance'] ?? '') ?: null;
        $situationFamiliale = trim($_POST['situation_familiale'] ?? 'celibataire');
        $nombreEnfants = (int)($_POST['nombre_enfants'] ?? 0);
        $salaireBase = str_replace([' ', "\xc2\xa0"], '', $_POST['salaire_base'] ?? '0');
        $salaireBase = str_replace(',', '.', $salaireBase);
        $dateEmbauche = trim($_POST['date_embauche'] ?? '');
        $typeContrat = trim($_POST['type_contrat'] ?? 'cdi');
        $matriculeCnps = trim($_POST['matricule_cnps'] ?? '');
        $numeroCompte = trim($_POST['numero_compte'] ?? '');
        $banque = trim($_POST['banque'] ?? '');
        $statut = trim($_POST['statut'] ?? 'actif');

        $errors = [];
        if (empty($nom)) $errors[] = 'Le nom est obligatoire';
        if (empty($prenom)) $errors[] = 'Le prénom est obligatoire';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';

        // Check email uniqueness (exclude current)
        if (empty($errors)) {
            $exists = $db->prepare("SELECT id FROM employes WHERE email = ? AND id != ?");
            $exists->execute([$email, $employeId]);
            if ($exists->fetch()) $errors[] = 'Cet email est déjà utilisé par un autre employé';
        }
        if (!empty($matricule)) {
            $existsMat = $db->prepare("SELECT id FROM employes WHERE matricule = ? AND id != ?");
            $existsMat->execute([$matricule, $employeId]);
            if ($existsMat->fetch()) $errors[] = 'Ce matricule est déjà utilisé';
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $oldEmp = $db->prepare("SELECT * FROM employes WHERE id = ?");
                $oldEmp->execute([$employeId]);
                $oldEmpData = $oldEmp->fetch();

                $stmtEmp = $db->prepare("UPDATE employes SET agence_id=?, service_id=?, nom=?, prenom=?, matricule=?, email=?, telephone=?, genre=?, date_naissance=?, situation_familiale=?, nombre_enfants=?, statut=? WHERE id=?");
                $stmtEmp->execute([$agenceId, $serviceId, $nom, $prenom, $matricule ?: null, $email, $telephone ?: null, $genre, $dateNaissance, $situationFamiliale, $nombreEnfants, $statut, $employeId]);

                // Sync to linked user account if exists
                if ($oldEmpData['utilisateur_id']) {
                    $db->prepare("UPDATE utilisateurs SET agence_id=?, service_id=?, nom=?, prenom=?, matricule=?, email=?, telephone=?, statut=? WHERE id=?")
                       ->execute([$agenceId, $serviceId, $nom, $prenom, $matricule ?: null, $email, $telephone ?: null, $statut, $oldEmpData['utilisateur_id']]);
                }

                if ($contratId > 0) {
                    $stmtContrat = $db->prepare("UPDATE contrats_employes SET salaire_base=?, date_embauche=?, type_contrat=?, matricule_cnps=?, numero_compte=?, banque=?, statut=? WHERE id=?");
                    $stmtContrat->execute([floatval($salaireBase), $dateEmbauche, $typeContrat, $matriculeCnps ?: null, $numeroCompte ?: null, $banque ?: null, $statut === 'actif' ? 'actif' : 'suspendu', $contratId]);
                }

                $db->commit();
                auditLog('modification_employe', 'rh', 'employes', $employeId, $oldEmpData, ['nom' => $nom, 'prenom' => $prenom, 'email' => $email]);
                flash('success', "Employé $prenom $nom modifié avec succès.");
                header('Location: employes.php'); exit;
            } catch (PDOException $e) {
                $db->rollBack();
                flash('danger', 'Erreur : ' . $e->getMessage());
                header('Location: employes.php'); exit;
            }
        } else {
            flash('danger', implode(' | ', $errors));
            header('Location: employes.php'); exit;
        }
    }

    // ── Supprimer employé ──
    if ($action === 'delete_employe' && hasPermission('rh', 'modifier')) {
        $employeId = (int)($_POST['employe_id'] ?? 0);
        if ($employeId <= 0) {
            flash('danger', 'Employé introuvable.');
            header('Location: employes.php'); exit;
        }

        try {
            $db->beginTransaction();

            $oldEmp = $db->prepare("SELECT * FROM employes WHERE id = ?");
            $oldEmp->execute([$employeId]);
            $oldEmpData = $oldEmp->fetch();

            // Unlink user account
            if ($oldEmpData['utilisateur_id']) {
                $db->prepare("UPDATE utilisateurs SET employe_id = NULL WHERE id = ?")->execute([$oldEmpData['utilisateur_id']]);
            }

            // Delete contract
            $db->prepare("DELETE FROM contrats_employes WHERE employe_id = ?")->execute([$employeId]);
            // Delete employee
            $db->prepare("DELETE FROM employes WHERE id = ?")->execute([$employeId]);

            $db->commit();
            auditLog('suppression_employe', 'rh', 'employes', $employeId, $oldEmpData, null);
            flash('success', 'Employé supprimé avec succès.');
            header('Location: employes.php'); exit;
        } catch (PDOException $e) {
            $db->rollBack();
            flash('danger', 'Erreur lors de la suppression : ' . $e->getMessage());
            header('Location: employes.php'); exit;
        }
    }
}

// ── Fetch data for display ──
$search = $_GET['q'] ?? '';
$filtreAgence = $_GET['agence'] ?? '';
$filtreContrat = $_GET['contrat'] ?? '';

$where = ["e.statut='actif'"];
$params = [];

if ($search) {
    $where[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ? OR e.email LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($filtreAgence) {
    $where[] = "e.agence_id = ?";
    $params[] = $filtreAgence;
}
if ($filtreContrat) {
    $where[] = "ce.type_contrat = ?";
    $params[] = $filtreContrat;
}

$whereSQL = implode(' AND ', $where);

$employes = $db->prepare("
    SELECT e.id, e.nom, e.prenom, e.matricule, e.email, e.telephone, e.statut, e.agence_id, e.service_id,
           e.genre, e.date_naissance, e.situation_familiale, e.nombre_enfants, e.utilisateur_id,
           a.nom as agence, s.nom as service,
           ce.id as contrat_id, ce.salaire_base, ce.date_embauche, ce.type_contrat, ce.matricule_cnps,
           ce.numero_compte, ce.banque, ce.statut as contrat_statut
    FROM employes e
    JOIN contrats_employes ce ON ce.employe_id = e.id
    LEFT JOIN agences a ON e.agence_id = a.id
    LEFT JOIN services s ON e.service_id = s.id
    WHERE $whereSQL
    ORDER BY e.nom, e.prenom
");
$employes->execute($params);
$employes = $employes->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <div>
        <h1 style="font-size:22px;font-weight:700;color:var(--text);margin:0">Liste des Employés</h1>
        <p style="color:var(--text3);font-size:13px;margin:4px 0 0"><?= count($employes) ?> employé(s)</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if (hasPermission('rh', 'modifier')): ?>
        <button onclick="openModal('modal-create-employe')" class="btn btn-primary"><i class="ph-bold ph-plus"></i> Créer un employé</button>
        <?php endif; ?>
        <?php if (hasPermission('rh', 'importer')): ?>
        <a href="import_employes.php" class="btn btn-outline"><i class="ph-bold ph-upload-simple"></i> Importer</a>
        <?php endif; ?>
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
                    <th>Contrat</th>
                    <th>Salaire</th>
                    <th>Embauche</th>
                    <th>Compte</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employes)): ?>
                <tr><td colspan="10" style="text-align:center;color:var(--text3);padding:40px">Aucun employé trouvé</td></tr>
                <?php endif; ?>
                <?php foreach ($employes as $e): ?>
                <tr>
                    <td><strong><?= sanitize($e['prenom'] . ' ' . $e['nom']) ?></strong></td>
                    <td><?= sanitize($e['matricule'] ?? '-') ?></td>
                    <td style="font-size:12px"><?= sanitize($e['email']) ?></td>
                    <td><?= sanitize($e['agence'] ?? '-') ?></td>
                    <td><span class="badge badge-info"><?= strtoupper(sanitize($e['type_contrat'])) ?></span></td>
                    <td style="white-space:nowrap"><?= formatMontant($e['salaire_base']) ?></td>
                    <td><?= sanitize($e['date_embauche']) ?></td>
                    <td>
                        <?php if ($e['utilisateur_id']): ?>
                        <span class="badge badge-success">Oui</span>
                        <?php else: ?>
                        <span class="badge badge-gray">Non</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($e['statut'] === 'actif'): ?>
                        <span class="badge badge-success">Actif</span>
                        <?php elseif ($e['statut'] === 'inactif'): ?>
                        <span class="badge badge-danger">Inactif</span>
                        <?php else: ?>
                        <span class="badge badge-warning">Suspendu</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap">
                        <?php if (hasPermission('rh', 'modifier')): ?>
                        <button class="btn btn-sm btn-outline" onclick='editEmploye(<?= json_encode($e, JSON_HEX_APOS) ?>)'><i class="ph-bold ph-pencil-simple"></i></button>
                        <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $e['id'] ?>, '<?= sanitize($e['prenom'] . ' ' . $e['nom']) ?>')"><i class="ph-bold ph-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Modal: Créer employé ── -->
<div class="modal-overlay" id="modal-create-employe">
    <div class="modal" style="max-width:700px;max-height:90vh">
        <div class="modal-header">
            <div class="modal-title">Créer un employé</div>
            <button class="modal-close" onclick="closeModal('modal-create-employe')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="create_employe">
            <div class="modal-body" style="overflow-y:auto;max-height:calc(90vh - 120px)">
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Nom *</label>
                        <input type="text" name="nom" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prénom *</label>
                        <input type="text" name="prenom" class="form-control" required>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Matricule</label>
                        <input type="text" name="matricule" class="form-control" placeholder="Auto-généré si vide">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Téléphone</label>
                        <input type="text" name="telephone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Genre</label>
                        <select name="genre" class="form-control">
                            <option value="M">Masculin</option>
                            <option value="F">Féminin</option>
                        </select>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Date de naissance</label>
                        <input type="date" name="date_naissance" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Situation familiale</label>
                        <select name="situation_familiale" class="form-control">
                            <option value="celibataire">Célibataire</option>
                            <option value="marie">Marié(e)</option>
                            <option value="divorce">Divorcé(e)</option>
                            <option value="veuf">Veuf/Veuve</option>
                        </select>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Agence</label>
                        <select name="agence_id" class="form-control" id="create-agence">
                            <option value="">Sélectionner...</option>
                            <?php foreach ($agences as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= sanitize($a['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Service</label>
                        <select name="service_id" class="form-control" id="create-service">
                            <option value="">Sélectionner...</option>
                            <?php foreach ($services as $s): ?>
                            <option value="<?= $s['id'] ?>" data-agence="<?= $s['agence_id'] ?>"><?= sanitize($s['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <hr style="border:0;border-top:1px solid var(--border);margin:16px 0">
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Salaire de base (FCFA) *</label>
                        <input type="text" name="salaire_base" class="form-control" required placeholder="250000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date d'embauche *</label>
                        <input type="date" name="date_embauche" class="form-control" required>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Type de contrat</label>
                        <select name="type_contrat" class="form-control">
                            <option value="cdi">CDI</option>
                            <option value="cdd">CDD</option>
                            <option value="stage">Stage</option>
                            <option value="prestataire">Prestataire</option>
                            <option value="interim">Intérim</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Matricule CNPS</label>
                        <input type="text" name="matricule_cnps" class="form-control">
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Numéro de compte (RIB)</label>
                        <input type="text" name="numero_compte" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Banque</label>
                        <input type="text" name="banque" class="form-control">
                    </div>
                </div>
                <hr style="border:0;border-top:1px solid var(--border);margin:16px 0">
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" name="creer_compte" id="create-creer-compte" onchange="toggleCompteSection(this)">
                        <strong>Créer un compte utilisateur</strong>
                        <span style="color:var(--text3);font-size:12px">(accès au système)</span>
                    </label>
                </div>
                <div id="compte-section" style="display:none">
                    <div class="form-group">
                        <label class="form-label">Rôle *</label>
                        <select name="role_id" id="create-role-id" class="form-control">
                            <option value="">Sélectionner...</option>
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= sanitize($r['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p style="color:var(--text3);font-size:12px">Un mot de passe temporaire sera généré automatiquement.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-create-employe')">Annuler</button>
                <button type="submit" class="btn btn-primary">Créer l'employé</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Modifier employé ── -->
<div class="modal-overlay" id="modal-edit-employe">
    <div class="modal" style="max-width:700px;max-height:90vh">
        <div class="modal-header">
            <div class="modal-title">Modifier l'employé</div>
            <button class="modal-close" onclick="closeModal('modal-edit-employe')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post" id="form-edit-employe">
            <input type="hidden" name="action" value="update_employe">
            <input type="hidden" name="employe_id" id="edit-employe-id">
            <input type="hidden" name="contrat_id" id="edit-contrat-id">
            <div class="modal-body" style="overflow-y:auto;max-height:calc(90vh - 120px)">
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Nom *</label>
                        <input type="text" name="nom" id="edit-nom" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prénom *</label>
                        <input type="text" name="prenom" id="edit-prenom" class="form-control" required>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Matricule</label>
                        <input type="text" name="matricule" id="edit-matricule" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="edit-email" class="form-control" required>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Téléphone</label>
                        <input type="text" name="telephone" id="edit-telephone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Genre</label>
                        <select name="genre" id="edit-genre" class="form-control">
                            <option value="M">Masculin</option>
                            <option value="F">Féminin</option>
                        </select>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Date de naissance</label>
                        <input type="date" name="date_naissance" id="edit-date-naissance" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Situation familiale</label>
                        <select name="situation_familiale" id="edit-situation" class="form-control">
                            <option value="celibataire">Célibataire</option>
                            <option value="marie">Marié(e)</option>
                            <option value="divorce">Divorcé(e)</option>
                            <option value="veuf">Veuf/Veuve</option>
                        </select>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Agence</label>
                        <select name="agence_id" id="edit-agence" class="form-control">
                            <option value="">Sélectionner...</option>
                            <?php foreach ($agences as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= sanitize($a['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Service</label>
                        <select name="service_id" id="edit-service" class="form-control">
                            <option value="">Sélectionner...</option>
                            <?php foreach ($services as $s): ?>
                            <option value="<?= $s['id'] ?>" data-agence="<?= $s['agence_id'] ?>"><?= sanitize($s['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Statut</label>
                    <select name="statut" id="edit-statut" class="form-control">
                        <option value="actif">Actif</option>
                        <option value="inactif">Inactif</option>
                        <option value="suspendu">Suspendu</option>
                    </select>
                </div>
                <hr style="border:0;border-top:1px solid var(--border);margin:16px 0">
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Salaire de base (FCFA) *</label>
                        <input type="text" name="salaire_base" id="edit-salaire" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date d'embauche *</label>
                        <input type="date" name="date_embauche" id="edit-date-embauche" class="form-control" required>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Type de contrat</label>
                        <select name="type_contrat" id="edit-type-contrat" class="form-control">
                            <option value="cdi">CDI</option>
                            <option value="cdd">CDD</option>
                            <option value="stage">Stage</option>
                            <option value="prestataire">Prestataire</option>
                            <option value="interim">Intérim</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Matricule CNPS</label>
                        <input type="text" name="matricule_cnps" id="edit-cnps" class="form-control">
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Numéro de compte (RIB)</label>
                        <input type="text" name="numero_compte" id="edit-compte" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Banque</label>
                        <input type="text" name="banque" id="edit-banque" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-edit-employe')">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Supprimer employé ── -->
<div class="modal-overlay" id="modal-delete-employe">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <div class="modal-title">Supprimer l'employé</div>
            <button class="modal-close" onclick="closeModal('modal-delete-employe')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="delete_employe">
            <input type="hidden" name="employe_id" id="delete-employe-id">
            <div class="modal-body">
                <p style="color:var(--text2)">Êtes-vous sûr de vouloir supprimer <strong id="delete-employe-name"></strong> ?</p>
                <p style="color:var(--danger);font-size:13px;margin-top:8px">Cette action est irréversible. Le contrat sera définitivement supprimé. Le compte utilisateur associé ne sera pas supprimé.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-delete-employe')">Annuler</button>
                <button type="submit" class="btn btn-danger">Supprimer</button>
            </div>
        </form>
    </div>
</div>

<script>
function editEmploye(data) {
    document.getElementById('edit-employe-id').value = data.id;
    document.getElementById('edit-contrat-id').value = data.contrat_id || '';
    document.getElementById('edit-nom').value = data.nom || '';
    document.getElementById('edit-prenom').value = data.prenom || '';
    document.getElementById('edit-matricule').value = data.matricule || '';
    document.getElementById('edit-email').value = data.email || '';
    document.getElementById('edit-telephone').value = data.telephone || '';
    document.getElementById('edit-genre').value = data.genre || 'M';
    document.getElementById('edit-date-naissance').value = data.date_naissance || '';
    document.getElementById('edit-situation').value = data.situation_familiale || 'celibataire';
    document.getElementById('edit-agence').value = data.agence_id || '';
    document.getElementById('edit-service').value = data.service_id || '';
    document.getElementById('edit-statut').value = data.statut || 'actif';
    document.getElementById('edit-salaire').value = data.salaire_base || '';
    document.getElementById('edit-date-embauche').value = data.date_embauche || '';
    document.getElementById('edit-type-contrat').value = data.type_contrat || 'cdi';
    document.getElementById('edit-cnps').value = data.matricule_cnps || '';
    document.getElementById('edit-compte').value = data.numero_compte || '';
    document.getElementById('edit-banque').value = data.banque || '';
    openModal('modal-edit-employe');
}

function confirmDelete(employeId, name) {
    document.getElementById('delete-employe-id').value = employeId;
    document.getElementById('delete-employe-name').textContent = name;
    openModal('modal-delete-employe');
}

function toggleCompteSection(checkbox) {
    document.getElementById('compte-section').style.display = checkbox.checked ? 'block' : 'none';
    document.getElementById('create-role-id').required = checkbox.checked;
}

// Filter services by agence (create modal)
document.getElementById('create-agence')?.addEventListener('change', function() {
    const agenceId = this.value;
    document.getElementById('create-service').querySelectorAll('option').forEach(opt => {
        if (!opt.value) return;
        opt.style.display = (!agenceId || opt.dataset.agence === agenceId) ? '' : 'none';
    });
    document.getElementById('create-service').value = '';
});

// Filter services by agence (edit modal)
document.getElementById('edit-agence')?.addEventListener('change', function() {
    const agenceId = this.value;
    document.getElementById('edit-service').querySelectorAll('option').forEach(opt => {
        if (!opt.value) return;
        opt.style.display = (!agenceId || opt.dataset.agence === agenceId) ? '' : 'none';
    });
    document.getElementById('edit-service').value = '';
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>