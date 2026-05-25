<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('admin');
$pageTitle = 'Administration';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
        try {
            $db->prepare("INSERT INTO utilisateurs (agence_id,service_id,role_id,nom,prenom,matricule,email,telephone,password_hash) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([$_POST['agence_id']??1,$_POST['service_id']??null,$_POST['role_id'],$_POST['nom'],$_POST['prenom'],$_POST['matricule']??null,$_POST['email'],$_POST['telephone']??null,$hash]);
            flash('success','Utilisateur créé.');
        } catch(PDOException $e) {
            flash('danger','Email ou matricule déjà utilisé.');
        }
        header('Location: index.php'); exit;
    }

    if ($action === 'toggle_user') {
        $uid = (int)$_POST['user_id'];
        $u = $db->prepare("SELECT statut FROM utilisateurs WHERE id=?");
        $u->execute([$uid]); $u = $u->fetch();
        $newStatut = $u['statut']==='actif' ? 'inactif' : 'actif';
        $db->prepare("UPDATE utilisateurs SET statut=? WHERE id=?")->execute([$newStatut,$uid]);
        flash('success','Statut mis à jour.');
        header('Location: index.php'); exit;
    }

    if ($action === 'affecter_role') {
        $uid = (int)$_POST['user_id'];
        $roleId = (int)$_POST['role_id'];
        $caisseId = !empty($_POST['caisse_id']) ? (int)$_POST['caisse_id'] : null;

        $db->prepare("UPDATE utilisateurs SET role_id=? WHERE id=?")->execute([$roleId, $uid]);

        // If caissier, assign as caisse responsable
        $roleR = $db->prepare("SELECT nom FROM roles WHERE id=?");
        $roleR->execute([$roleId]);
        $roleNom = $roleR->fetchColumn();

        if ($roleNom === 'caissier' && $caisseId) {
            // Remove previous caisse assignment for this user
            $db->prepare("UPDATE caisses SET responsable_id=NULL WHERE responsable_id=?")->execute([$uid]);
            // Assign new caisse
            $db->prepare("UPDATE caisses SET responsable_id=? WHERE id=?")->execute([$uid, $caisseId]);
        } elseif ($roleNom !== 'caissier') {
            // If no longer caissier, remove caisse assignment
            $db->prepare("UPDATE caisses SET responsable_id=NULL WHERE responsable_id=?")->execute([$uid]);
        }

        auditLog('affecter_role','admin','utilisateurs',$uid);
        flash('success','Rôle affecté avec succès.');
        header('Location: index.php'); exit;
    }

    if ($action === 'update_user') {
        $uid = (int)$_POST['user_id'];
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $matricule = !empty($_POST['matricule']) ? trim($_POST['matricule']) : null;
        $email = trim($_POST['email']);
        $telephone = !empty($_POST['telephone']) ? trim($_POST['telephone']) : null;
        $agenceId = !empty($_POST['agence_id']) ? (int)$_POST['agence_id'] : null;
        $serviceId = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
        $responsableId = !empty($_POST['responsable_id']) ? (int)$_POST['responsable_id'] : null;

        try {
            $db->prepare("UPDATE utilisateurs SET nom=?, prenom=?, matricule=?, email=?, telephone=?, agence_id=?, service_id=? WHERE id=?")
               ->execute([$nom, $prenom, $matricule, $email, $telephone, $agenceId, $serviceId, $uid]);

            // Update supérieur hiérarchique on the user's service
            if ($serviceId) {
                $db->prepare("UPDATE services SET responsable_id=? WHERE id=?")
                   ->execute([$responsableId, $serviceId]);
            }

            // Reset password if provided
            if (!empty($_POST['password'])) {
                $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
                $db->prepare("UPDATE utilisateurs SET password_hash=? WHERE id=?")->execute([$hash, $uid]);
            }

            auditLog('update_user','admin','utilisateurs',$uid);
            flash('success','Utilisateur modifié.');
        } catch(PDOException $e) {
            flash('danger','Email ou matricule déjà utilisé par un autre utilisateur.');
        }
        header('Location: index.php?tab=utilisateurs'); exit;
    }

    if ($action === 'update_entreprise') {
        $logo = $_POST['logo_actuel'] ?? '';
        if (!empty($_FILES['logo_file']['name'])) {
            $allowed = ['image/png','image/jpeg','image/svg+xml','image/gif'];
            if (in_array($_FILES['logo_file']['type'], $allowed) && $_FILES['logo_file']['size'] <= 2*1024*1024) {
                $ext = pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION);
                $filename = 'logo_' . time() . '.' . $ext;
                $dest = __DIR__ . '/../../uploads/logos/' . $filename;
                if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $dest)) {
                    if ($logo && file_exists(__DIR__ . '/../../uploads/logos/' . basename($logo))) {
                        @unlink(__DIR__ . '/../../uploads/logos/' . basename($logo));
                    }
                    $logo = BASE_URL . '/uploads/logos/' . $filename;
                }
            }
        }
        if (isset($_POST['supprimer_logo']) && $_POST['supprimer_logo'] === '1') {
            if ($logo && file_exists(__DIR__ . '/../../uploads/logos/' . basename($logo))) {
                @unlink(__DIR__ . '/../../uploads/logos/' . basename($logo));
            }
            $logo = '';
        }
        $theme = $_POST['theme'] ?? 'default';
        $police = $_POST['police'] ?? 'Segoe UI';
        $db->prepare("UPDATE entreprises SET nom=?,sigle=?,adresse=?,telephone=?,email=?,registre_commerce=?,numero_contribuable=?,logo=?,theme=?,police=?,devise=?,exercice_courant=? WHERE id=1")
           ->execute([$_POST['nom'],$_POST['sigle']??'',$_POST['adresse']??'',$_POST['telephone']??'',$_POST['email']??'',$_POST['registre_commerce']??'',$_POST['numero_contribuable']??'',$logo,$theme,$police,$_POST['devise']??'FCFA',$_POST['exercice']]);
        flash('success','Paramètres entreprise mis à jour.');
        header('Location: index.php?tab=entreprise'); exit;
    }

    // ── Rôles ──
    if ($action === 'create_role') {
        $newPerms = [];
        $permAll = isset($_POST['perm_all']) && $_POST['perm_all'] === '1';
        if ($permAll) {
            $newPerms = ['all' => true];
        } else {
            foreach ($_POST['perms'] ?? [] as $mod => $actions) {
                foreach ($actions as $act => $val) {
                    if ($val === '1') {
                        $newPerms[$mod][$act] = true;
                    }
                }
                if (isset($newPerms[$mod]['all']) && $newPerms[$mod]['all']) {
                    $newPerms[$mod] = ['all' => true];
                }
            }
        }
        try {
            $db->prepare("INSERT INTO roles (nom, description, permissions) VALUES (?,?,?)")
               ->execute([trim($_POST['nom']), trim($_POST['description']??''), json_encode($newPerms)]);
            auditLog('create_role', 'admin', 'roles', $db->lastInsertId());
            flash('success','Rôle créé avec succès.');
        } catch(PDOException $e) {
            flash('danger','Ce nom de rôle existe déjà.');
        }
        header('Location: index.php?tab=roles'); exit;
    }
    if ($action === 'update_role') {
        $newPerms = [];
        $permAll = isset($_POST['perm_all']) && $_POST['perm_all'] === '1';
        if ($permAll) {
            $newPerms = ['all' => true];
        } else {
            foreach ($_POST['perms'] ?? [] as $mod => $actions) {
                foreach ($actions as $act => $val) {
                    if ($val === '1') {
                        $newPerms[$mod][$act] = true;
                    }
                }
                if (isset($newPerms[$mod]['all']) && $newPerms[$mod]['all']) {
                    $newPerms[$mod] = ['all' => true];
                }
            }
        }
        try {
            $db->prepare("UPDATE roles SET nom=?, description=?, permissions=? WHERE id=?")
               ->execute([trim($_POST['nom']), trim($_POST['description']??''), json_encode($newPerms), (int)$_POST['role_id']]);
            auditLog('update_role', 'admin', 'roles', (int)$_POST['role_id']);
            flash('success','Rôle mis à jour.');
        } catch(PDOException $e) {
            flash('danger','Ce nom de rôle existe déjà.');
        }
        header('Location: index.php?tab=roles'); exit;
    }

    // ── Offices ──
    if ($action === 'create_agence') {
        try {
            $db->prepare("INSERT INTO agences (entreprise_id, code, nom, adresse, telephone, responsable) VALUES (?,?,?,?,?,?)")
               ->execute([1, strtoupper(trim($_POST['code'])), trim($_POST['nom']), trim($_POST['adresse']??''), trim($_POST['telephone']??''), trim($_POST['responsable']??'')]);
            auditLog('create_agence', 'admin', 'agences', $db->lastInsertId());
            flash('success','Office créé avec succès.');
        } catch(PDOException $e) {
            flash('danger','Code office déjà utilisé.');
        }
        header('Location: index.php?tab=agences'); exit;
    }
    if ($action === 'update_agence') {
        try {
            $db->prepare("UPDATE agences SET code=?, nom=?, adresse=?, telephone=?, responsable=? WHERE id=?")
               ->execute([strtoupper(trim($_POST['code'])), trim($_POST['nom']), trim($_POST['adresse']??''), trim($_POST['telephone']??''), trim($_POST['responsable']??''), (int)$_POST['agence_id']]);
            auditLog('update_agence', 'admin', 'agences', (int)$_POST['agence_id']);
            flash('success','Office mis à jour.');
        } catch(PDOException $e) {
            flash('danger','Code office déjà utilisé.');
        }
        header('Location: index.php?tab=agences'); exit;
    }
    if ($action === 'toggle_agence') {
        $aid = (int)$_POST['agence_id'];
        $a = $db->prepare("SELECT statut FROM agences WHERE id=?");
        $a->execute([$aid]); $a = $a->fetch();
        $newStatut = $a['statut']==='actif' ? 'inactif' : 'actif';
        $db->prepare("UPDATE agences SET statut=? WHERE id=?")->execute([$newStatut,$aid]);
        auditLog('toggle_agence', 'admin', 'agences', $aid);
        flash('success','Statut office mis à jour.');
        header('Location: index.php?tab=agences'); exit;
    }

    // ── Services ──
    if ($action === 'create_service') {
        try {
            $respId = !empty($_POST['responsable_id']) ? (int)$_POST['responsable_id'] : null;
            $db->prepare("INSERT INTO services (agence_id, code, nom, responsable, responsable_id) VALUES (?,?,?,?,?)")
               ->execute([(int)$_POST['agence_id'], strtoupper(trim($_POST['code'])), trim($_POST['nom']), trim($_POST['responsable']??''), $respId]);
            auditLog('create_service', 'admin', 'services', $db->lastInsertId());
            flash('success','Service créé avec succès.');
        } catch(PDOException $e) {
            flash('danger','Code service déjà utilisé.');
        }
        header('Location: index.php?tab=agences'); exit;
    }
    if ($action === 'update_service') {
        try {
            $respId = !empty($_POST['responsable_id']) ? (int)$_POST['responsable_id'] : null;
            $db->prepare("UPDATE services SET agence_id=?, code=?, nom=?, responsable=?, responsable_id=? WHERE id=?")
               ->execute([(int)$_POST['agence_id'], strtoupper(trim($_POST['code'])), trim($_POST['nom']), trim($_POST['responsable']??''), $respId, (int)$_POST['service_id']]);
            auditLog('update_service', 'admin', 'services', (int)$_POST['service_id']);
            flash('success','Service mis à jour.');
        } catch(PDOException $e) {
            flash('danger','Code service déjà utilisé.');
        }
        header('Location: index.php?tab=agences'); exit;
    }
    if ($action === 'toggle_service') {
        $sid = (int)$_POST['service_id'];
        $s = $db->prepare("SELECT statut FROM services WHERE id=?");
        $s->execute([$sid]); $s = $s->fetch();
        $newStatut = $s['statut']==='actif' ? 'inactif' : 'actif';
        $db->prepare("UPDATE services SET statut=? WHERE id=?")->execute([$newStatut,$sid]);
        auditLog('toggle_service', 'admin', 'services', $sid);
        flash('success','Statut service mis à jour.');
        header('Location: index.php?tab=agences'); exit;
    }

    // ── RADNEX Config ──
    if ($action === 'update_radnex_config') {
        $provider = $_POST['provider'] ?? 'ollama';
        if (!in_array($provider, ['ollama','openai','openrouter','custom'])) $provider = 'ollama';

        $newConfig = [
            'provider'          => $provider,
            'use_ollama'       => $provider === 'ollama',
            'ollama_url'        => trim($_POST['ollama_url'] ?? 'http://localhost:11434/v1/chat/completions'),
            'ollama_model'      => trim($_POST['ollama_model'] ?? 'llama3.1:8b'),
            'openai_key'        => trim($_POST['openai_key'] ?? ''),
            'openai_model'      => trim($_POST['openai_model'] ?? 'gpt-4o-mini'),
            'openrouter_key'    => trim($_POST['openrouter_key'] ?? ''),
            'openrouter_model'  => trim($_POST['openrouter_model'] ?? 'openrouter/free'),
            'custom_url'        => trim($_POST['custom_url'] ?? ''),
            'custom_key'        => trim($_POST['custom_key'] ?? ''),
            'custom_model'      => trim($_POST['custom_model'] ?? ''),
            'assistant_name'    => trim($_POST['assistant_name'] ?? 'RADNEX') ?: 'RADNEX',
            'system_prompt'     => trim($_POST['system_prompt'] ?? ''),
            'language'          => 'fr',
            'web_search_enabled'=> isset($_POST['web_search']),
            'timeout'           => max(5, min(120, (int)($_POST['timeout'] ?? 30))),
        ];

        if (empty($newConfig['system_prompt'])) {
            $newConfig['system_prompt'] = "Tu es " . $newConfig['assistant_name'] . ", un assistant IA intelligent spécialisé en finance et gestion d'entreprise. Tu as accès à Internet pour rechercher les informations les plus récentes. Sois concis, précis, professionnel et utilise le français. Pour les questions financières, fournis des analyses claires avec des exemples concrets. Cite toujours tes sources quand tu utilises la recherche web.";
        }

        $configFile = __DIR__ . '/../radnex/config.php';
        $export = var_export($newConfig, true);
        file_put_contents($configFile, "<?php\nreturn " . $export . ";\n");

        auditLog('update_radnex_config', 'admin');
        flash('success', 'Configuration RADNEX enregistrée.');
        header('Location: index.php?tab=radnex'); exit;
    }

}

$utilisateurs = $db->query("SELECT u.*, r.nom as role_nom, a.nom as agence_nom, c.libelle as caisse_nom, c.id as caisse_id, s.nom as service_nom, s.responsable_id as service_responsable_id, CONCAT(rh.nom,' ',rh.prenom) as responsable_nom FROM utilisateurs u JOIN roles r ON u.role_id=r.id LEFT JOIN agences a ON u.agence_id=a.id LEFT JOIN caisses c ON c.responsable_id=u.id LEFT JOIN services s ON u.service_id=s.id LEFT JOIN utilisateurs rh ON s.responsable_id=rh.id ORDER BY u.nom")->fetchAll();
$roles = $db->query("SELECT * FROM roles ORDER BY nom")->fetchAll();
$caisses = $db->query("SELECT * FROM caisses ORDER BY libelle")->fetchAll();
$agences = $db->query("SELECT * FROM agences ORDER BY nom")->fetchAll();
$actifs = $db->query("SELECT id, nom, prenom, role_id FROM utilisateurs WHERE statut='actif' ORDER BY nom, prenom")->fetchAll();
$services = $db->query("SELECT s.*, a.nom as agence_nom, CONCAT(u.nom,' ',u.prenom) as responsable_nom FROM services s LEFT JOIN agences a ON s.agence_id=a.id LEFT JOIN utilisateurs u ON s.responsable_id=u.id ORDER BY s.nom")->fetchAll();
$entreprise = $db->query("SELECT * FROM entreprises WHERE id=1")->fetch();

// ── Définition des modules et actions disponibles ──
$permModules = [
  'caisse'            => ['label' => 'Caisse',              'actions' => ['saisir' => 'Saisir opérations', 'consulter' => 'Consulter', 'valider' => 'Valider transferts', 'annuler' => 'Annuler opérations']],
  'operations_caisse' => ['label' => 'Opérations caisse',  'actions' => ['saisir' => 'Saisir', 'consulter' => 'Consulter', 'annuler' => 'Annuler']],
  'tresorerie'        => ['label' => 'Trésorerie',         'actions' => ['saisir' => 'Saisir opérations', 'consulter' => 'Consulter', 'rapprocher' => 'Rapprochement', 'valider_operations' => 'Valider opérations', 'importer_releve' => 'Importer relevé']],
  'engagements'       => ['label' => 'Engagements',        'actions' => ['creer' => 'Créer demandes', 'consulter' => 'Voir toutes', 'consulter_propres' => 'Voir ses demandes', 'valider_hierarchie' => 'Valider N1', 'valider_comptable' => 'Valider compta', 'valider_daf' => 'Valider DAF', 'executer' => 'Exécuter']],
  'comptabilite'      => ['label' => 'Comptabilité',       'actions' => ['saisir' => 'Saisir écritures', 'consulter' => 'Consulter', 'valider' => 'Valider écritures', 'cloturer' => 'Clôturer période']],
  'budget'            => ['label' => 'Budget',              'actions' => ['creer' => 'Créer budget', 'consulter' => 'Consulter', 'valider' => 'Valider budget']],
  'reporting'         => ['label' => 'Reporting',          'actions' => ['consulter' => 'Consulter']],
  'audit'             => ['label' => 'Audit',               'actions' => ['consulter' => 'Consulter journal']],
  'admin'             => ['label' => 'Administration',      'actions' => ['all' => 'Accès total admin', 'utilisateurs' => 'Gérer utilisateurs', 'roles' => 'Gérer rôles', 'parametres' => 'Paramètres']],
  'paie'              => ['label' => 'Paie',               'actions' => ['consulter' => 'Consulter', 'gerer_rubriques' => 'Gérer rubriques', 'gerer_bulletins' => 'Gérer bulletins', 'valider_paie' => 'Valider paie', 'declarer' => 'Déclarations']],
  'rh'                => ['label' => 'Ressources Humaines',  'actions' => ['consulter' => 'Consulter', 'importer' => 'Importer employés', 'modifier' => 'Modifier employés']],
  'bons_commande'     => ['label' => 'Bons de commande',   'actions' => ['consulter' => 'Consulter', 'creer' => 'Créer BC', 'valider' => 'Valider BC', 'recevoir' => 'Réceptionner']],
  'cloture'           => ['label' => 'Clôture exercice',   'actions' => ['consulter' => 'Consulter', 'gerer_exercice' => 'Gérer exercices', 'ecritures_inventaire' => 'Écritures d\'inventaire', 'cloturer' => 'Clôturer']],
  'compta_analytique' => ['label' => 'Compta analytique',  'actions' => ['consulter' => 'Consulter', 'configurer' => 'Configurer axes', 'affecter' => 'Affecter']],
  'ordre_mission'     => ['label' => 'Ordres de mission',  'actions' => ['creer' => 'Créer missions', 'consulter' => 'Voir toutes', 'consulter_propres' => 'Voir ses missions', 'valider_hierarchie' => 'Valider N1', 'valider_daf' => 'Valider DAF', 'executer' => 'Exécuter']],
  'decharge'          => ['label' => 'Décharges',           'actions' => ['creer' => 'Créer décharge', 'consulter' => 'Consulter', 'valider' => 'Valider', 'executer' => 'Exécuter']],
  'radnex'            => ['label' => 'Radnex AI',          'actions' => ['consulter' => 'Utiliser chat']],
  'referentiels'      => ['label' => 'Référentiels',       'actions' => ['consulter' => 'Consulter', 'saisir' => 'Saisir']],
];

$activeTab = $_GET['tab'] ?? 'utilisateurs';
$editRole = isset($_GET['edit_role']) ? (int)$_GET['edit_role'] : null;
$editAgence = isset($_GET['edit_agence']) ? (int)$_GET['edit_agence'] : null;
$editService = isset($_GET['edit_service']) ? (int)$_GET['edit_service'] : null;
$editRoleData = $editRole ? $db->prepare("SELECT * FROM roles WHERE id=?") : null;
if ($editRoleData) { $editRoleData->execute([$editRole]); $editRoleData = $editRoleData->fetch(); if (!$editRoleData) $editRole = null; }
$editAgenceData = $editAgence ? $db->prepare("SELECT * FROM agences WHERE id=?") : null;
if ($editAgenceData) { $editAgenceData->execute([$editAgence]); $editAgenceData = $editAgenceData->fetch(); if (!$editAgenceData) $editAgence = null; }
$editServiceData = $editService ? $db->prepare("SELECT s.*, a.nom as agence_nom, CONCAT(u.nom,' ',u.prenom) as responsable_nom FROM services s LEFT JOIN agences a ON s.agence_id=a.id LEFT JOIN utilisateurs u ON s.responsable_id=u.id WHERE s.id=?") : null;
if ($editServiceData) { $editServiceData->execute([$editService]); $editServiceData = $editServiceData->fetch(); if (!$editServiceData) $editService = null; }

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
  <div><h1>Administration</h1><p>Gestion des utilisateurs, rôles et paramètres</p></div>
</div>

<div class="tab-wrapper">
  <div class="tabs">
    <button class="tab <?= $activeTab==='utilisateurs'?'active':'' ?>" data-tab="tab-users">Utilisateurs</button>
    <button class="tab <?= $activeTab==='entreprise'?'active':'' ?>" data-tab="tab-entreprise">Entreprise</button>
    <button class="tab <?= $activeTab==='roles'?'active':'' ?>" data-tab="tab-roles">Rôles</button>
    <button class="tab <?= $activeTab==='agences'?'active':'' ?>" data-tab="tab-agences">Offices & Services</button>
    <button class="tab <?= $activeTab==='radnex'?'active':'' ?>" data-tab="tab-radnex"><i class="fa-solid fa-robot"></i> IA RADNEX</button>
  </div>

  <!-- UTILISATEURS -->
  <div class="tab-content <?= $activeTab==='utilisateurs'?'active':'' ?>" id="tab-users">
    <div class="d-flex justify-between align-center mb-16">
      <span style="font-weight:600"><?= count($utilisateurs) ?> utilisateur(s)</span>
      <button class="btn btn-primary" onclick="openModal('modal-new-user')">+ Nouvel utilisateur</button>
    </div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Nom</th><th>Matricule</th><th>Email</th><th>Rôle</th><th>Service</th><th>Supérieur</th><th>Office</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach($utilisateurs as $u): ?>
            <tr data-id="<?= $u['id'] ?>" data-role="<?= $u['role_id'] ?>" data-caisse="<?= $u['caisse_id']??'' ?>">
              <td>
                <div class="d-flex align-center gap-8">
                  <div style="width:32px;height:32px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0"><?= strtoupper(substr($u['prenom'],0,1).substr($u['nom'],0,1)) ?></div>
                  <div><div style="font-weight:600"><?= sanitize($u['nom'].' '.$u['prenom']) ?></div><div style="font-size:11.5px;color:var(--text3)"><?= sanitize($u['telephone']??'') ?></div></div>
                </div>
              </td>
              <td><?= sanitize($u['matricule']??'—') ?></td>
              <td><?= sanitize($u['email']) ?></td>
              <td><span class="badge badge-info" style="font-size:11px"><?= sanitize($u['role_nom']) ?></span></td>
              <td><?= sanitize($u['service_nom']??'—') ?></td>
              <td><?= sanitize($u['responsable_nom']??'—') ?></td>
              <td><?= sanitize($u['agence_nom']??'—') ?></td>
              <td><span class="badge <?= $u['statut']==='actif'?'badge-success':($u['statut']==='inactif'?'badge-gray':'badge-danger') ?>"><?= ucfirst($u['statut']) ?></span></td>
              <td style="white-space:nowrap">
                <button class="btn btn-ghost btn-sm" onclick="editUser(<?= $u['id'] ?>, '<?= sanitize(addslashes($u['nom'])) ?>', '<?= sanitize(addslashes($u['prenom'])) ?>', '<?= sanitize(addslashes($u['matricule']??'')) ?>', '<?= sanitize(addslashes($u['email'])) ?>', '<?= sanitize(addslashes($u['telephone']??'')) ?>', <?= $u['agence_id']??'null' ?>, <?= $u['service_id']??'null' ?>, <?= $u['service_responsable_id']??'null' ?>)"><i class="fa-solid fa-pen"></i></button>
                <button class="btn btn-outline btn-sm" onclick="affecterRole(<?= $u['id'] ?>, <?= $u['role_id'] ?>, <?= $u['caisse_id']??'null' ?>)">Affecter</button>
                <form method="post" style="display:inline"><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button type="submit" class="btn btn-ghost btn-sm"><?= $u['statut']==='actif'?'Désactiver':'Activer' ?></button></form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ENTREPRISE -->
  <div class="tab-content <?= $activeTab==='entreprise'?'active':'' ?>" id="tab-entreprise">
    <div class="card" style="max-width:600px">
      <div class="card-header"><span class="card-title">Paramètres de l'entreprise</span></div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_entreprise">
        <input type="hidden" name="logo_actuel" value="<?= sanitize($entreprise['logo']??'') ?>">
        <div class="card-body">
          <div class="form-row-2">
            <div class="form-group">
              <label class="form-label">Raison sociale <span class="req">*</span></label>
              <input type="text" name="nom" class="form-control" value="<?= sanitize($entreprise['nom']??'') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Sigle / Abréviation</label>
              <input type="text" name="sigle" class="form-control" value="<?= sanitize($entreprise['sigle']??'') ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Adresse</label>
            <textarea name="adresse" class="form-control" rows="2"><?= sanitize($entreprise['adresse']??'') ?></textarea>
          </div>
          <div class="form-row-2">
            <div class="form-group">
              <label class="form-label">Téléphone</label>
              <input type="text" name="telephone" class="form-control" value="<?= sanitize($entreprise['telephone']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?= sanitize($entreprise['email']??'') ?>">
            </div>
          </div>
          <div class="form-row-2">
            <div class="form-group">
              <label class="form-label">Registre de Commerce</label>
              <input type="text" name="registre_commerce" class="form-control" value="<?= sanitize($entreprise['registre_commerce']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Numéro Contribuable</label>
              <input type="text" name="numero_contribuable" class="form-control" value="<?= sanitize($entreprise['numero_contribuable']??'') ?>">
            </div>
          </div>

          <div style="border-top:1px solid var(--border);margin:16px 0;padding-top:16px">
            <strong style="font-size:13px;color:var(--text2)">Apparence</strong>
          </div>

          <div class="form-group">
            <label class="form-label">Logo de l'entreprise</label>
            <?php if (!empty($entreprise['logo'])): ?>
            <div style="margin-bottom:8px;display:flex;align-items:center;gap:12px">
              <img src="<?= sanitize($entreprise['logo']) ?>" alt="Logo" style="height:48px;width:auto;border-radius:6px;border:1px solid var(--border)">
              <label style="font-size:12px;color:var(--text3);display:flex;align-items:center;gap:4px;cursor:pointer">
                <input type="checkbox" name="supprimer_logo" value="1"> Supprimer le logo
              </label>
            </div>
            <?php endif; ?>
            <input type="file" name="logo_file" class="form-control" accept="image/png,image/jpeg,image/svg+xml,image/gif">
            <small class="text-muted">PNG, JPG, SVG ou GIF — 2 Mo max. Apparaît dans la barre latérale et les documents imprimés.</small>
          </div>

          <div class="form-group">
            <label class="form-label"><i class="fa-solid fa-palette"></i> Moteur de Thème Ultra-Moderne</label>
            <div style="background:var(--surface2);border-radius:var(--radius-lg);padding:20px;border:1px solid var(--border)">
              <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px;margin-bottom:16px" id="theme-grid">
                <?php
                $themes = [
                  'default'    => ['BrenFinance',    '#1a4f8a','#0ea87e', '<i class="fa-solid fa-building-columns"></i>'],
                  'wallstreet' => ['Wall Street',    '#0a2540','#00ff88', '<i class="fa-solid fa-chart-line"></i>'],
                  'cyberpunk'  => ['Cyberpunk',      '#6c00ff','#00f0ff', '<i class="fa-solid fa-wand-magic-sparkles"></i>'],
                  'aurora'     => ['Aurora',         '#065535','#22d4ee', '<i class="fa-solid fa-meteor"></i>'],
                  'executive'  => ['Executive',      '#001d3d','#ffd60a', '<i class="fa-solid fa-briefcase"></i>'],
                  'solar'      => ['Solar Flare',    '#c2410c','#fbbf24', '<i class="fa-solid fa-sun"></i>'],
                  'ocean'      => ['Ocean Depth',    '#003566','#48cae4', '<i class="fa-solid fa-water"></i>'],
                  'royal'      => ['Royal Purple',   '#4a0072','#ffd700', '<i class="fa-solid fa-crown"></i>'],
                  'emerald'    => ['Emerald Luxe',   '#064e3b','#10b981', '<i class="fa-solid fa-gem"></i>'],
                ];
                $curTheme = $entreprise['theme'] ?? 'default';
                foreach ($themes as $val => [$label, $c1, $c2, $icon]): ?>
                <div class="theme-swatch" data-theme="<?= $val ?>"
                     style="background:linear-gradient(135deg,<?= $c1 ?> 0%,<?= $c2 ?> 100%);border-radius:10px;padding:14px 10px;text-align:center;cursor:pointer;border:2px solid <?= $curTheme===$val?'var(--accent)':'transparent' ?>;transition:all .25s ease;position:relative;overflow:hidden;opacity:.88"
                     onmouseenter="this.style.opacity='1'" onmouseleave="this.style.opacity='.88'"
                     onclick="previewTheme('<?= $val ?>');document.getElementById('sel-theme').value='<?= $val ?>'">
                  <div style="font-size:24px;margin-bottom:6px"><?= $icon ?></div>
                  <div style="font-size:11px;font-weight:500;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.4)"><?= $label ?></div>
                  <?php if ($curTheme===$val): ?>
                  <div style="position:absolute;top:8px;right:8px;background:var(--accent);color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:12px"><i class="fa-solid fa-check"></i></div>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
              <select name="theme" class="form-control" id="sel-theme" style="display:none">
                <?php foreach ($themes as $val => [$label, $c1, $c2, $icon]): ?>
                <option value="<?= $val ?>" <?= $curTheme===$val?'selected':'' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
              <div style="font-size:12px;color:var(--text3);text-align:center">
                <i class="fa-solid fa-lightbulb"></i> Cliquez sur un thème pour la prévisualisation en direct • Le choix est sauvegardé automatiquement
              </div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Police de caractères</label>
            <select name="police" class="form-control">
              <?php
              $fonts = ['Segoe UI'=>'Segoe UI','Roboto'=>'Roboto','Open Sans'=>'Open Sans','Lato'=>'Lato','Poppins'=>'Poppins','Inter'=>'Inter','Arial'=>'Arial','Verdana'=>'Verdana'];
              $curFont = $entreprise['police'] ?? 'Segoe UI';
              foreach ($fonts as $val => $label): ?>
              <option value="<?= $val ?>" <?= $curFont===$val?'selected':'' ?> style="font-family:'<?= $val ?>',sans-serif"><?= $label ?></option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Les polices Google Fonts (Roboto, Open Sans, Lato, Poppins, Inter) seront chargées automatiquement.</small>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label class="form-label">Devise</label>
              <input type="text" name="devise" class="form-control" value="<?= sanitize($entreprise['devise']??'FCFA') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Exercice courant</label>
              <input type="number" name="exercice" class="form-control" value="<?= $entreprise['exercice_courant']??date('Y') ?>" min="2000" max="2100">
            </div>
          </div>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
        </div>
      </form>
    </div>
  </div>

<script>
// Theme preview highlight
var selTheme = document.getElementById('sel-theme');
if (selTheme) {
  selTheme.addEventListener('change', function() {
    document.querySelectorAll('#theme-preview > div').forEach(function(d) {
      d.style.borderColor = d.dataset.theme === selTheme.value ? 'var(--text)' : 'transparent';
    });
  });
}
</script>

  <!-- RÔLES -->
  <div class="tab-content <?= $activeTab==='roles'?'active':'' ?>" id="tab-roles">
    <?php if ($editRole && $editRoleData): ?>
    <!-- ── VUE ÉDITION RÔLE ── -->
    <a href="index.php?tab=roles" class="btn btn-outline btn-sm mb-16" style="text-decoration:none">&larr; Retour à la liste</a>
    <div class="card" style="max-width:720px">
      <div class="card-header"><span class="card-title">Modifier le rôle : <?= sanitize($editRoleData['nom']) ?></span></div>
      <form method="post">
        <input type="hidden" name="action" value="update_role">
        <input type="hidden" name="role_id" value="<?= $editRoleData['id'] ?>">
        <div class="card-body">
          <div class="form-row-2">
            <div class="form-group">
              <label class="form-label">Nom du rôle <span class="req">*</span></label>
              <input type="text" name="nom" class="form-control" required value="<?= htmlspecialchars($editRoleData['nom']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($editRoleData['description']??'') ?></textarea>
            </div>
          </div>
          <div class="form-group" style="margin-top:12px">
            <label class="perm-header">
              <input type="checkbox" name="perm_all" value="1" id="edit-perm-all" onchange="toggleAllPerms(this, 'edit-perms-grid')" <?= (!empty(json_decode($editRoleData['permissions']??'{}', true)['all'])) ? 'checked' : '' ?>> Accès total (super administrateur)
            </label>
          </div>
          <div id="edit-perms-grid" class="perm-grid">
            <?php
            $editPerms = json_decode($editRoleData['permissions']??'{}', true);
            $editIsAll = !empty($editPerms['all']);
            foreach($permModules as $modKey => $mod):
              $modAll = !$editIsAll && !empty($editPerms[$modKey]['all']);
            ?>
            <div class="perm-module">
              <div class="perm-module-header">
                <label><input type="checkbox" onchange="toggleModulePerms(this, 'edit-')" data-mod="<?= $modKey ?>" <?= $modAll ? 'checked' : '' ?>> <strong><?= $mod['label'] ?></strong></label>
              </div>
              <div class="perm-actions">
                <?php foreach($mod['actions'] as $actKey => $actLabel):
                  $checked = $editIsAll || $modAll || (!empty($editPerms[$modKey][$actKey]));
                ?>
                <label class="perm-check"><input type="checkbox" name="perms[<?= $modKey ?>][<?= $actKey ?>]" value="1" data-mod="<?= $modKey ?>" class="edit-perm-cb" <?= $checked ? 'checked' : '' ?>> <?= $actLabel ?></label>
                <?php endforeach; ?>
                <label class="perm-check perm-check-all"><input type="checkbox" name="perms[<?= $modKey ?>][all]" value="1" data-mod="<?= $modKey ?>" class="edit-perm-cb" onchange="toggleModuleAll(this, 'edit-')" <?= $modAll ? 'checked' : '' ?>> Tout le module</label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="card-footer d-flex gap-8">
          <a href="index.php?tab=roles" class="btn btn-outline">Annuler</a>
          <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
      </form>
    </div>
    <?php else: ?>
    <!-- ── VUE LISTE RÔLES ── -->
    <div class="d-flex justify-between align-center mb-16">
      <span style="font-weight:600"><?= count($roles) ?> rôle(s)</span>
      <a href="index.php?tab=roles&new_role=1" class="btn btn-primary">+ Nouveau rôle</a>
    </div>

    <?php if (isset($_GET['new_role'])): ?>
    <!-- ── VUE CREATION RÔLE ── -->
    <div class="card mb-24">
      <div class="card-header">
        <span class="card-title">Nouveau rôle</span>
        <a href="index.php?tab=roles" class="btn btn-ghost btn-sm" style="margin-left:auto"><i class="fa-solid fa-arrow-left"></i> Retour</a>
      </div>
      <form method="post">
        <input type="hidden" name="action" value="create_role">
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Nom du rôle <span class="req">*</span></label>
            <input type="text" name="nom" class="form-control" required placeholder="ex: responsable_caisse" autofocus>
          </div>
          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Description du rôle et de ses responsabilités"></textarea>
          </div>
          <div class="form-group">
            <label class="perm-header">
              <input type="checkbox" name="perm_all" value="1" onchange="toggleAllPerms(this, 'new-perms-grid')"> Accès total (super administrateur)
            </label>
          </div>
          <div id="new-perms-grid" class="perm-grid">
            <?php foreach($permModules as $modKey => $mod): ?>
            <div class="perm-module">
              <div class="perm-module-header">
                <label><input type="checkbox" onchange="toggleModulePerms(this, 'new-')" data-mod="<?= $modKey ?>"> <strong><?= $mod['label'] ?></strong></label>
              </div>
              <div class="perm-actions">
                <?php foreach($mod['actions'] as $actKey => $actLabel): ?>
                <label class="perm-check"><input type="checkbox" name="perms[<?= $modKey ?>][<?= $actKey ?>]" value="1" data-mod="<?= $modKey ?>" class="new-perm-cb"> <?= $actLabel ?></label>
                <?php endforeach; ?>
                <label class="perm-check perm-check-all"><input type="checkbox" name="perms[<?= $modKey ?>][all]" value="1" data-mod="<?= $modKey ?>" class="new-perm-cb" onchange="toggleModuleAll(this, 'new-')"> Tout le module</label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="card-footer d-flex justify-between">
          <a href="index.php?tab=roles" class="btn btn-outline">Annuler</a>
          <button type="submit" class="btn btn-primary">Créer le rôle</button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <!-- TABLEAU DES RÔLES -->
    <div class="card">
      <div class="card-header"><span class="card-title">Liste des rôles</span></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Nom</th><th>Description</th><th>Permissions</th><th>Nb utilisateurs</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach($roles as $r):
              $nb = $db->prepare("SELECT COUNT(*) as n FROM utilisateurs WHERE role_id=?");
              $nb->execute([$r['id']]); $nb = $nb->fetch()['n'];
              $rPerms = json_decode($r['permissions']??'{}', true);
              $permLabels = [];
              if (isset($rPerms['all']) && $rPerms['all']) {
                $permLabels[] = '<span class="badge badge-danger" style="font-size:10px">Accès total</span>';
              } else {
                foreach ($rPerms as $mod => $acts) {
                  if (isset($permModules[$mod])) {
                    $permLabels[] = '<span class="badge badge-info" style="font-size:10px">' . $permModules[$mod]['label'] . '</span>';
                  }
                }
              }
            ?>
            <tr>
              <td><span class="badge badge-info"><?= sanitize($r['nom']) ?></span></td>
              <td><?= sanitize($r['description']??'') ?></td>
              <td style="max-width:220px"><?= implode(' ', $permLabels) ?: '<span style="color:var(--text3);font-size:12px">Aucune</span>' ?></td>
              <td><?= $nb ?></td>
              <td>
                <a href="index.php?tab=roles&edit_role=<?= $r['id'] ?>" class="btn btn-ghost btn-sm">Modifier</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MATRICE DES PERMISSIONS (dépliable) -->
    <details class="perm-details mt-16">
      <summary class="perm-details-summary">Matrice des autorisations — vue globale</summary>
      <div class="card mt-16">
        <div class="table-wrap" style="overflow-x:auto">
          <table class="perm-matrix">
            <thead>
              <tr>
                <th class="perm-matrix-module">Module</th>
                <th class="perm-matrix-action">Action</th>
                <?php foreach($roles as $r): ?>
                <th class="perm-matrix-role" title="<?= htmlspecialchars($r['description']??'') ?>"><?= sanitize($r['nom']) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach($permModules as $modKey => $mod): ?>
              <?php $actIdx = 0; $actCount = count($mod['actions']) + 1; ?>
              <?php foreach($mod['actions'] as $actKey => $actLabel): ?>
              <tr>
                <?php if ($actIdx === 0): ?>
                <td rowspan="<?= $actCount ?>" class="perm-matrix-module"><strong><?= $mod['label'] ?></strong></td>
                <?php endif; ?>
                <td class="perm-matrix-action"><?= $actLabel ?></td>
                <?php foreach($roles as $r):
                  $rPerms = json_decode($r['permissions']??'{}', true);
                  $hasAll = isset($rPerms['all']) && $rPerms['all'];
                  $hasModAll = isset($rPerms[$modKey]['all']) && $rPerms[$modKey]['all'];
                  $hasAct = isset($rPerms[$modKey][$actKey]) && $rPerms[$modKey][$actKey];
                  $granted = $hasAll || $hasModAll || $hasAct;
                ?>
                <td class="perm-matrix-cell <?= $granted ? 'granted' : 'denied' ?>"><?= $granted ? '&#10003;' : '' ?></td>
                <?php endforeach; ?>
              </tr>
              <?php $actIdx++; endforeach; ?>
              <tr>
                <td class="perm-matrix-action perm-matrix-all">Tout le module</td>
                <?php foreach($roles as $r):
                  $rPerms = json_decode($r['permissions']??'{}', true);
                  $hasAll = isset($rPerms['all']) && $rPerms['all'];
                  $hasModAll = isset($rPerms[$modKey]['all']) && $rPerms[$modKey]['all'];
                  $granted = $hasAll || $hasModAll;
                ?>
                <td class="perm-matrix-cell <?= $granted ? 'granted' : 'denied' ?>"><?= $granted ? '&#10003;' : '' ?></td>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </details>
    <?php endif; ?>
  </div>

  <!-- AGENCES & SERVICES -->
  <div class="tab-content <?= $activeTab==='agences'?'active':'' ?>" id="tab-agences">

    <?php if ($editAgence && $editAgenceData): ?>
    <!-- ── ÉDITION AGENCE ── -->
    <a href="index.php?tab=agences" class="btn btn-outline btn-sm mb-16" style="text-decoration:none">&larr; Retour à la liste</a>
    <div class="card" style="max-width:560px">
      <div class="card-header"><span class="card-title">Modifier l'office : <?= sanitize($editAgenceData['nom']) ?></span></div>
      <form method="post">
        <input type="hidden" name="action" value="update_agence">
        <input type="hidden" name="agence_id" value="<?= $editAgenceData['id'] ?>">
        <div class="card-body">
          <div class="form-row-2">
            <div class="form-group">
              <label class="form-label">Code <span class="req">*</span></label>
              <input type="text" name="code" class="form-control" required value="<?= htmlspecialchars($editAgenceData['code']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Nom <span class="req">*</span></label>
              <input type="text" name="nom" class="form-control" required value="<?= htmlspecialchars($editAgenceData['nom']) ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Adresse</label>
            <input type="text" name="adresse" class="form-control" value="<?= htmlspecialchars($editAgenceData['adresse']??'') ?>">
          </div>
          <div class="form-row-2">
            <div class="form-group">
              <label class="form-label">Téléphone</label>
              <input type="text" name="telephone" class="form-control" value="<?= htmlspecialchars($editAgenceData['telephone']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Responsable</label>
              <input type="text" name="responsable" class="form-control" value="<?= htmlspecialchars($editAgenceData['responsable']??'') ?>">
            </div>
          </div>
        </div>
        <div class="card-footer d-flex gap-8">
          <a href="index.php?tab=agences" class="btn btn-outline">Annuler</a>
          <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
      </form>
    </div>

    <?php elseif ($editService && $editServiceData): ?>
    <!-- ── ÉDITION SERVICE ── -->
    <a href="index.php?tab=agences" class="btn btn-outline btn-sm mb-16" style="text-decoration:none">&larr; Retour à la liste</a>
    <div class="card" style="max-width:500px">
      <div class="card-header"><span class="card-title">Modifier le service : <?= sanitize($editServiceData['nom']) ?></span></div>
      <form method="post">
        <input type="hidden" name="action" value="update_service">
        <input type="hidden" name="service_id" value="<?= $editServiceData['id'] ?>">
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Office <span class="req">*</span></label>
            <select name="agence_id" class="form-control" required>
              <?php foreach($agences as $a): ?>
              <option value="<?= $a['id'] ?>" <?= $a['id']==$editServiceData['agence_id']?'selected':'' ?>><?= sanitize($a['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row-2">
            <div class="form-group">
              <label class="form-label">Code <span class="req">*</span></label>
              <input type="text" name="code" class="form-control" required value="<?= htmlspecialchars($editServiceData['code']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Nom <span class="req">*</span></label>
              <input type="text" name="nom" class="form-control" required value="<?= htmlspecialchars($editServiceData['nom']) ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Responsable (texte libre)</label>
            <input type="text" name="responsable" class="form-control" value="<?= htmlspecialchars($editServiceData['responsable']??'') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Responsable hiérarchique (utilisateur)</label>
            <select name="responsable_id" class="form-control">
              <option value="">— Aucun —</option>
              <?php foreach($actifs as $a): ?>
              <option value="<?= $a['id'] ?>" <?= isset($editServiceData['responsable_id']) && $a['id']==$editServiceData['responsable_id']?'selected':'' ?>><?= sanitize($a['nom'].' '.$a['prenom']) ?></option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Ce valideur pourra valider les engagements N+1 de ce service.</small>
          </div>
        </div>
        <div class="card-footer d-flex gap-8">
          <a href="index.php?tab=agences" class="btn btn-outline">Annuler</a>
          <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
      </form>
    </div>

    <?php else: ?>
    <!-- ── VUE LISTE AGENCES & SERVICES ── -->
    <div style="display:flex;flex-direction:column;gap:16px">
      <div>
        <div class="d-flex justify-between align-center mb-16">
          <span style="font-weight:600"><?= count($agences) ?> office(s)</span>
          <button class="btn btn-primary btn-sm" onclick="openModal('modal-new-agence')">+ Nouvel office</button>
        </div>
        <div class="card">
          <div class="table-wrap">
            <table>
              <thead><tr><th>Code</th><th>Nom</th><th>Responsable</th><th>Statut</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach($agences as $a): ?>
                <tr data-id="<?= $a['id'] ?>" data-code="<?= htmlspecialchars($a['code'], ENT_QUOTES) ?>" data-nom="<?= htmlspecialchars($a['nom'], ENT_QUOTES) ?>" data-adresse="<?= htmlspecialchars($a['adresse']??'', ENT_QUOTES) ?>" data-telephone="<?= htmlspecialchars($a['telephone']??'', ENT_QUOTES) ?>" data-responsable="<?= htmlspecialchars($a['responsable']??'', ENT_QUOTES) ?>">
                  <td><code><?= sanitize($a['code']) ?></code></td>
                  <td><?= sanitize($a['nom']) ?></td>
                  <td><?= sanitize($a['responsable']??'—') ?></td>
                  <td><span class="badge <?= $a['statut']==='actif'?'badge-success':'badge-gray' ?>"><?= ucfirst($a['statut']) ?></span></td>
                  <td>
                    <a href="index.php?tab=agences&edit_agence=<?= $a['id'] ?>" class="btn btn-ghost btn-sm">Modifier</a>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="action" value="toggle_agence">
                      <input type="hidden" name="agence_id" value="<?= $a['id'] ?>">
                      <button type="submit" class="btn btn-ghost btn-sm"><?= $a['statut']==='actif'?'Désactiver':'Activer' ?></button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div>
        <div class="d-flex justify-between align-center mb-16">
          <span style="font-weight:600"><?= count($services) ?> service(s)</span>
          <button class="btn btn-primary btn-sm" onclick="openModal('modal-new-service')">+ Nouveau service</button>
        </div>
        <div class="card">
          <div class="table-wrap">
            <table>
              <thead><tr><th>Code</th><th>Nom</th><th>Office</th><th>Responsable</th><th>Statut</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach($services as $s): ?>
                <tr data-id="<?= $s['id'] ?>" data-code="<?= htmlspecialchars($s['code'], ENT_QUOTES) ?>" data-nom="<?= htmlspecialchars($s['nom'], ENT_QUOTES) ?>" data-agence-id="<?= $s['agence_id'] ?>" data-responsable-id="<?= $s['responsable_id']??'' ?>" data-responsable="<?= htmlspecialchars($s['responsable']??'', ENT_QUOTES) ?>">
                  <td><code><?= sanitize($s['code']) ?></code></td>
                  <td><?= sanitize($s['nom']) ?></td>
                  <td><?= sanitize($s['agence_nom']??'—') ?></td>
                  <td><?= sanitize($s['responsable_nom']??'—') ?></td>
                  <td><span class="badge <?= $s['statut']==='actif'?'badge-success':'badge-gray' ?>"><?= ucfirst($s['statut']) ?></span></td>
                  <td>
                    <a href="index.php?tab=agences&edit_service=<?= $s['id'] ?>" class="btn btn-ghost btn-sm">Modifier</a>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="action" value="toggle_service">
                      <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
                      <button type="submit" class="btn btn-ghost btn-sm"><?= $s['statut']==='actif'?'Désactiver':'Activer' ?></button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- RADNEX AI Config -->
  <?php
  $radnexConfigFile = __DIR__ . '/../radnex/config.php';
  $radnexCfg = file_exists($radnexConfigFile) ? require $radnexConfigFile : [];
  $rProvider    = $radnexCfg['provider'] ?? ($radnexCfg['use_ollama'] ?? false ? 'ollama' : 'openrouter');
  $rOllamaUrl   = $radnexCfg['ollama_url'] ?? 'http://localhost:11434/v1/chat/completions';
  $rOllamaModel = $radnexCfg['ollama_model'] ?? 'llama3.1:8b';
  $rOpenaiKey   = $radnexCfg['openai_key'] ?? '';
  $rOpenaiModel = $radnexCfg['openai_model'] ?? 'gpt-4o-mini';
  $rOrKey       = $radnexCfg['openrouter_key'] ?? '';
  $rOrModel     = $radnexCfg['openrouter_model'] ?? 'openrouter/free';
  $rCustomUrl   = $radnexCfg['custom_url'] ?? '';
  $rCustomKey   = $radnexCfg['custom_key'] ?? '';
  $rCustomModel = $radnexCfg['custom_model'] ?? '';
  $rName        = $radnexCfg['assistant_name'] ?? 'RADNEX';
  $rPrompt      = $radnexCfg['system_prompt'] ?? '';
  $rWebSearch   = $radnexCfg['web_search_enabled'] ?? true;
  $rTimeout     = $radnexCfg['timeout'] ?? 30;

  // Test connection
  $radnexTest = '';
  if (isset($_GET['radnex_test'])) {
      $testProvider = $rProvider;
      $testUrl = ''; $testKey = ''; $testModel = '';
      switch ($testProvider) {
          case 'ollama': $testUrl = $rOllamaUrl; $testModel = $rOllamaModel; break;
          case 'openai': $testUrl = 'https://api.openai.com/v1/chat/completions'; $testKey = $rOpenaiKey; $testModel = $rOpenaiModel; break;
          case 'openrouter': $testUrl = 'https://openrouter.ai/api/v1/chat/completions'; $testKey = $rOrKey; $testModel = $rOrModel; break;
          case 'custom': $testUrl = $rCustomUrl; $testKey = $rCustomKey; $testModel = $rCustomModel; break;
      }
      if ($testUrl) {
          $testPayload = json_encode(['model'=>$testModel,'messages'=>[['role'=>'user','content'=>'Dis bonjour en 5 mots max']],'max_tokens'=>50]);
          $testHeaders = ['Content-Type: application/json'];
          if ($testKey) $testHeaders[] = 'Authorization: Bearer '.$testKey;
          if ($testProvider === 'openrouter') { $testHeaders[] = 'HTTP-Referer: '.BASE_URL; $testHeaders[] = 'X-Title: BrenFinance'; }
          $testCh = curl_init($testUrl);
          curl_setopt_array($testCh, [CURLOPT_RETURNTRANSFER=>1,CURLOPT_POST=>1,CURLOPT_HTTPHEADER=>$testHeaders,CURLOPT_POSTFIELDS=>$testPayload,CURLOPT_TIMEOUT=>60]);
          $testRes = curl_exec($testCh); $testErr = curl_error($testCh); curl_close($testCh);
          if ($testErr) {
              $radnexTest = '<div class="toast toast-danger" style="display:block;position:static;margin-bottom:16px"><span class="toast-msg">Erreur de connexion : '.sanitize($testErr).'</span></div>';
          } else {
              $tj = json_decode($testRes, true);
              if (isset($tj['choices'][0]['message']['content'])) {
                  $radnexTest = '<div class="toast toast-success" style="display:block;position:static;margin-bottom:16px"><span class="toast-msg">Test réussi ! Réponse : '.sanitize($tj['choices'][0]['message']['content']).'</span></div>';
              } elseif (isset($tj['error'])) {
                  $errMsg = is_array($tj['error']) ? sanitize($tj['error']['message'] ?? json_encode($tj['error'])) : sanitize($tj['error']);
                  $radnexTest = '<div class="toast toast-danger" style="display:block;position:static;margin-bottom:16px"><span class="toast-msg">Erreur API : '.$errMsg.'</span></div>';
              } else {
                  $radnexTest = '<div class="toast toast-danger" style="display:block;position:static;margin-bottom:16px"><span class="toast-msg">Réponse inattendue : '.sanitize(mb_substr($testRes,0,200)).'</span></div>';
              }
          }
      }
  }
  ?>
  <div class="tab-content <?= $activeTab==='radnex'?'active':'' ?>" id="tab-radnex">
    <?= $radnexTest ?>

    <form method="post">
      <input type="hidden" name="action" value="update_radnex_config">

      <div class="card mb-16">
        <div class="card-header"><span class="card-title"><i class="fa-solid fa-robot" style="color:var(--primary)"></i> Fournisseur IA</span></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Fournisseur <span class="req">*</span></label>
            <select name="provider" id="radnex-provider" class="form-control" onchange="toggleRadnexFields()">
              <option value="ollama" <?= $rProvider==='ollama'?'selected':'' ?>>Ollama (Local, gratuit)</option>
              <option value="openai" <?= $rProvider==='openai'?'selected':'' ?>>OpenAI (GPT-4, GPT-4o-mini...)</option>
              <option value="openrouter" <?= $rProvider==='openrouter'?'selected':'' ?>>OpenRouter (Cloud, modèles gratuits)</option>
              <option value="custom" <?= $rProvider==='custom'?'selected':'' ?>>Personnalisé (endpoint compatible OpenAI)</option>
            </select>
          </div>

          <!-- Ollama fields -->
          <div class="radnex-fields" id="radnex-ollama" style="<?= $rProvider!=='ollama'?'display:none':'' ?>">
            <div style="padding:12px;background:var(--surface);border-radius:var(--radius);margin-bottom:12px;font-size:13px;color:var(--text3)">
              <strong>Ollama</strong> permet d'utiliser l'IA gratuitement en local.
              <a href="https://ollama.com/download" target="_blank">Télécharger Ollama</a> ·
              Après installation, lancez <code>ollama pull llama3.1:8b</code>
            </div>
            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label">URL API Ollama</label>
                <input type="text" name="ollama_url" class="form-control" value="<?= sanitize($rOllamaUrl) ?>" placeholder="http://localhost:11434/v1/chat/completions">
              </div>
              <div class="form-group">
                <label class="form-label">Modèle</label>
                <input type="text" name="ollama_model" class="form-control" value="<?= sanitize($rOllamaModel) ?>" placeholder="llama3.1:8b">
              </div>
            </div>
          </div>

          <!-- OpenAI fields -->
          <div class="radnex-fields" id="radnex-openai" style="<?= $rProvider!=='openai'?'display:none':'' ?>">
            <div style="padding:12px;background:var(--surface);border-radius:var(--radius);margin-bottom:12px;font-size:13px;color:var(--text3)">
              <strong>OpenAI</strong> — Créez une clé sur <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>
            </div>
            <div class="form-group">
              <label class="form-label">Clé API OpenAI <span class="req">*</span></label>
              <input type="password" name="openai_key" class="form-control" value="<?= sanitize($rOpenaiKey) ?>" placeholder="sk-...">
            </div>
            <div class="form-group">
              <label class="form-label">Modèle</label>
              <select name="openai_model" class="form-control">
                <option value="gpt-4o-mini" <?= $rOpenaiModel==='gpt-4o-mini'?'selected':'' ?>>gpt-4o-mini (rapide, économique)</option>
                <option value="gpt-4o" <?= $rOpenaiModel==='gpt-4o'?'selected':'' ?>>gpt-4o (équilibré)</option>
                <option value="gpt-4.1-mini" <?= $rOpenaiModel==='gpt-4.1-mini'?'selected':'' ?>>gpt-4.1-mini</option>
                <option value="gpt-4.1" <?= $rOpenaiModel==='gpt-4.1'?'selected':'' ?>>gpt-4.1</option>
              </select>
            </div>
          </div>

          <!-- OpenRouter fields -->
          <div class="radnex-fields" id="radnex-openrouter" style="<?= $rProvider!=='openrouter'?'display:none':'' ?>">
            <div style="padding:12px;background:var(--surface);border-radius:var(--radius);margin-bottom:12px;font-size:13px;color:var(--text3)">
              <strong>OpenRouter</strong> — Accédez à des centaines de modèles avec une seule clé.
              <a href="https://openrouter.ai/keys" target="_blank">Créer une clé</a>
            </div>
            <div class="form-group">
              <label class="form-label">Clé API OpenRouter <span class="req">*</span></label>
              <input type="password" name="openrouter_key" class="form-control" value="<?= sanitize($rOrKey) ?>" placeholder="sk-or-v1-...">
            </div>
            <div class="form-group">
              <label class="form-label">Modèle</label>
              <select name="openrouter_model" class="form-control">
                <option value="openrouter/free" <?= $rOrModel==='openrouter/free'?'selected':'' ?>>openrouter/free (auto, gratuit)</option>
                <option value="deepseek/deepseek-r1-0528:free" <?= $rOrModel==='deepseek/deepseek-r1-0528:free'?'selected':'' ?>>deepseek-r1 (gratuit)</option>
                <option value="meta-llama/llama-4-maverick:free" <?= $rOrModel==='meta-llama/llama-4-maverick:free'?'selected':'' ?>>llama-4-maverick (gratuit)</option>
                <option value="google/gemma-3-27b-it:free" <?= $rOrModel==='google/gemma-3-27b-it:free'?'selected':'' ?>>gemma-3-27b (gratuit)</option>
                <option value="mistralai/mistral-small-3.1-24b-instruct:free" <?= $rOrModel==='mistralai/mistral-small-3.1-24b-instruct:free'?'selected':'' ?>>mistral-small-3.1 (gratuit)</option>
              </select>
            </div>
          </div>

          <!-- Custom fields -->
          <div class="radnex-fields" id="radnex-custom" style="<?= $rProvider!=='custom'?'display:none':'' ?>">
            <div style="padding:12px;background:var(--surface);border-radius:var(--radius);margin-bottom:12px;font-size:13px;color:var(--text3)">
              <strong>Personnalisé</strong> — Tout endpoint compatible avec l'API OpenAI Chat Completions.
            </div>
            <div class="form-group">
              <label class="form-label">URL API <span class="req">*</span></label>
              <input type="text" name="custom_url" class="form-control" value="<?= sanitize($rCustomUrl) ?>" placeholder="https://api.example.com/v1/chat/completions">
            </div>
            <div class="form-group">
              <label class="form-label">Clé API</label>
              <input type="password" name="custom_key" class="form-control" value="<?= sanitize($rCustomKey) ?>" placeholder="Laisser vide si pas nécessaire">
            </div>
            <div class="form-group">
              <label class="form-label">Modèle <span class="req">*</span></label>
              <input type="text" name="custom_model" class="form-control" value="<?= sanitize($rCustomModel) ?>" placeholder="my-model-name">
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-16">
        <div class="card-header"><span class="card-title"><i class="fa-solid fa-sliders" style="color:var(--primary)"></i> Paramètres de l'assistant</span></div>
        <div class="card-body">
          <div class="form-row-2">
            <div class="form-group">
              <label class="form-label">Nom de l'assistant</label>
              <input type="text" name="assistant_name" class="form-control" value="<?= sanitize($rName) ?>" placeholder="RADNEX">
            </div>
            <div class="form-group">
              <label class="form-label">Timeout (secondes)</label>
              <input type="number" name="timeout" class="form-control" value="<?= (int)$rTimeout ?>" min="5" max="120">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Prompt système</label>
            <textarea name="system_prompt" class="form-control" rows="4" placeholder="Instructions pour l'assistant IA..."><?= sanitize($rPrompt) ?></textarea>
            <small style="color:var(--text3)">Définit le comportement et la personnalité de l'assistant. Laisser vide pour le prompt par défaut.</small>
          </div>
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px">
              <input type="checkbox" name="web_search" <?= $rWebSearch?'checked':'' ?>>
              Activer la recherche web
            </label>
          </div>
        </div>
      </div>

      <div class="d-flex gap-8">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Enregistrer</button>
        <a href="?tab=radnex&radnex_test=1" class="btn btn-outline"><i class="fa-solid fa-vial"></i> Tester la connexion</a>
      </div>
    </form>
  </div>
</div>

<!-- REFERENTIELS SUPPRIMÉ -->

<!-- Modal: Nouvel utilisateur -->
<div class="modal-overlay" id="modal-new-user">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <div class="modal-title">Créer un utilisateur</div>
      <button class="modal-close" onclick="closeModal('modal-new-user')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="create_user">
      <div class="modal-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Nom <span class="req">*</span></label>
            <input type="text" name="nom" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Prénom <span class="req">*</span></label>
            <input type="text" name="prenom" class="form-control" required>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Matricule</label>
            <input type="text" name="matricule" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input type="text" name="telephone" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email <span class="req">*</span></label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="form-row-3">
          <div class="form-group">
            <label class="form-label">Rôle <span class="req">*</span></label>
            <select name="role_id" class="form-control" required>
              <?php foreach($roles as $r): ?>
              <option value="<?= $r['id'] ?>"><?= sanitize($r['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Office</label>
            <select name="agence_id" class="form-control">
              <?php foreach($agences as $a): ?>
              <option value="<?= $a['id'] ?>"><?= sanitize($a['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Service</label>
            <select name="service_id" class="form-control">
              <option value="">Aucun</option>
              <?php foreach($services as $s): ?>
              <option value="<?= $s['id'] ?>"><?= sanitize($s['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Mot de passe <span class="req">*</span></label>
          <input type="password" name="password" class="form-control" required minlength="8">
          <small class="text-muted">Minimum 8 caractères.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-new-user')">Annuler</button>
        <button type="submit" class="btn btn-primary">Créer l'utilisateur</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Modifier utilisateur -->
<div class="modal-overlay" id="modal-edit-user">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <div class="modal-title">Modifier l'utilisateur</div>
      <button class="modal-close" onclick="closeModal('modal-edit-user')">&times;</button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="update_user">
      <input type="hidden" name="user_id" id="edit-user-id">
      <div class="modal-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Nom <span class="req">*</span></label>
            <input type="text" name="nom" id="edit-user-nom" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Prénom <span class="req">*</span></label>
            <input type="text" name="prenom" id="edit-user-prenom" class="form-control" required>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Matricule</label>
            <input type="text" name="matricule" id="edit-user-matricule" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input type="text" name="telephone" id="edit-user-telephone" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email <span class="req">*</span></label>
          <input type="email" name="email" id="edit-user-email" class="form-control" required>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Office</label>
            <select name="agence_id" id="edit-user-agence" class="form-control">
              <?php foreach($agences as $a): ?>
              <option value="<?= $a['id'] ?>"><?= sanitize($a['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Service</label>
            <select name="service_id" id="edit-user-service" class="form-control" onchange="loadResponsable(this.value)">
              <option value="">Aucun</option>
              <?php foreach($services as $s): ?>
              <option value="<?= $s['id'] ?>" data-responsable-id="<?= $s['responsable_id']??'' ?>"><?= sanitize($s['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group" id="edit-user-responsable-wrap" style="display:none">
          <label class="form-label">Supérieur hiérarchique (du service)</label>
          <select name="responsable_id" id="edit-user-responsable" class="form-control">
            <option value="">-- Aucun --</option>
            <?php foreach($actifs as $a): ?>
            <option value="<?= $a['id'] ?>"><?= sanitize($a['nom'].' '.$a['prenom']) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">Le supérieur hiérarchique valide les engagements au niveau N+1.</small>
        </div>
        <div class="form-group">
          <label class="form-label">Nouveau mot de passe</label>
          <input type="password" name="password" id="edit-user-password" class="form-control" minlength="8" placeholder="Laisser vide pour ne pas changer">
          <small class="text-muted">Laisser vide pour conserver le mot de passe actuel.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-edit-user')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Nouvel office -->
<div class="modal-overlay" id="modal-new-agence">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <div class="modal-title">Nouvel office</div>
      <button class="modal-close" onclick="closeModal('modal-new-agence')">&times;</button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="create_agence">
      <div class="modal-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Code <span class="req">*</span></label>
            <input type="text" name="code" class="form-control" required placeholder="ex: DLA">
          </div>
          <div class="form-group">
            <label class="form-label">Nom <span class="req">*</span></label>
            <input type="text" name="nom" class="form-control" required placeholder="Nom de l'office">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Adresse</label>
          <input type="text" name="adresse" class="form-control" placeholder="Adresse physique">
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input type="text" name="telephone" class="form-control" placeholder="Numéro de téléphone">
          </div>
          <div class="form-group">
            <label class="form-label">Responsable</label>
            <input type="text" name="responsable" class="form-control" placeholder="Nom du responsable">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-new-agence')">Annuler</button>
        <button type="submit" class="btn btn-primary">Créer l'office</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Nouveau service -->
<div class="modal-overlay" id="modal-new-service">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <div class="modal-title">Nouveau service</div>
      <button class="modal-close" onclick="closeModal('modal-new-service')">&times;</button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="create_service">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Office <span class="req">*</span></label>
          <select name="agence_id" class="form-control" required>
            <?php foreach($agences as $a): ?>
            <option value="<?= $a['id'] ?>"><?= sanitize($a['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Code <span class="req">*</span></label>
            <input type="text" name="code" class="form-control" required placeholder="ex: COMPTA">
          </div>
          <div class="form-group">
            <label class="form-label">Nom <span class="req">*</span></label>
            <input type="text" name="nom" class="form-control" required placeholder="Nom du service">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Responsable (texte libre)</label>
          <input type="text" name="responsable" class="form-control" placeholder="Nom du responsable">
        </div>
        <div class="form-group">
          <label class="form-label">Responsable hiérarchique (utilisateur)</label>
          <select name="responsable_id" class="form-control">
            <option value="">— Aucun —</option>
            <?php foreach($actifs as $a): ?>
            <option value="<?= $a['id'] ?>"><?= sanitize($a['nom'].' '.$a['prenom']) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">Ce valideur pourra valider les engagements N+1 de ce service.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-new-service')">Annuler</button>
        <button type="submit" class="btn btn-primary">Créer le service</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Affecter rôle -->
<div class="modal-overlay" id="modal-affecter-role">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <div class="modal-title">Affecter un rôle</div>
      <button class="modal-close" onclick="closeModal('modal-affecter-role')">&times;</button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="affecter_role">
      <input type="hidden" name="user_id" id="aff-user-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Rôle <span class="req">*</span></label>
          <select name="role_id" id="aff-role-id" class="form-control" required onchange="toggleCaisseSelect()">
            <?php foreach($roles as $r): ?>
            <option value="<?= $r['id'] ?>" data-nom="<?= sanitize($r['nom']) ?>"><?= sanitize($r['nom']) ?> — <?= sanitize($r['description']??'') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" id="caisse-field" style="display:none">
          <label class="form-label">Caisse affectée</label>
          <select name="caisse_id" id="aff-caisse-id" class="form-control">
            <option value="">-- Aucune --</option>
            <?php foreach($caisses as $c): ?>
            <option value="<?= $c['id'] ?>"><?= sanitize($c['libelle']) ?> (<?= sanitize($c['code']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">Le caissier ne pourra opérer que sur cette caisse.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-affecter-role')">Annuler</button>
        <button type="submit" class="btn btn-primary">Affecter</button>
      </div>
    </form>
  </div>
</div>

<!-- REFERENTIELS MODALS SUPPRIMÉS -->

<script>
// Activate tab based on URL
const urlTab = new URLSearchParams(location.search).get('tab');
if (urlTab) {
  document.querySelectorAll('.tabs .tab').forEach(t => {
    t.classList.toggle('active', t.dataset.tab === 'tab-' + urlTab);
  });
  document.querySelectorAll('.tab-content').forEach(c => {
    c.classList.toggle('active', c.id === 'tab-' + urlTab);
  });
}

/* ─── PERMISSIONS MANAGEMENT ─────────────────────────────────── */
function toggleAllPerms(checkbox, gridId) {
  const grid = document.getElementById(gridId);
  grid.querySelectorAll('input[type=checkbox]').forEach(cb => {
    cb.checked = checkbox.checked;
    cb.disabled = checkbox.checked;
  });
  grid.style.opacity = checkbox.checked ? '.4' : '1';
  grid.style.pointerEvents = checkbox.checked ? 'none' : '';
}

function toggleModulePerms(checkbox, prefix) {
  const mod = checkbox.dataset.mod;
  const cbs = document.querySelectorAll('.' + prefix + 'perm-cb[data-mod="' + mod + '"]');
  cbs.forEach(cb => { cb.checked = checkbox.checked; });
}

function toggleModuleAll(checkbox, prefix) {
  const mod = checkbox.dataset.mod;
  const cbs = document.querySelectorAll('.' + prefix + 'perm-cb[data-mod="' + mod + '"]');
  cbs.forEach(cb => { if (cb !== checkbox) cb.checked = checkbox.checked; });
}

// Initialize super-admin checkbox state on page load
document.addEventListener('DOMContentLoaded', () => {
  const editAllCb = document.getElementById('edit-perm-all');
  if (editAllCb && editAllCb.checked) {
    toggleAllPerms(editAllCb, 'edit-perms-grid');
  }
});

/* ─── ROLE AFFECTATION ──────────────────────────────────────── */
function editTypeDepense(id, code, libelle) {
  document.getElementById('edit-td-id').value = id;
  document.getElementById('edit-td-code').value = code;
  document.getElementById('edit-td-libelle').value = libelle;
  openModal('modal-edit-type-depense');
}

function editDestination(id, code, libelle) {
  document.getElementById('edit-dst-id').value = id;
  document.getElementById('edit-dst-code').value = code;
  document.getElementById('edit-dst-libelle').value = libelle;
  openModal('modal-edit-destination');
}

function editTypeOperation(id, code, libelle, sens, categorie) {
  document.getElementById('edit-to-id').value = id;
  document.getElementById('edit-to-code').value = code;
  document.getElementById('edit-to-libelle').value = libelle;
  document.getElementById('edit-to-sens').value = sens;
  document.getElementById('edit-to-categorie').value = categorie;
  openModal('modal-edit-type-operation');
}

function editUser(userId, nom, prenom, matricule, email, telephone, agenceId, serviceId, responsableId) {
  document.getElementById('edit-user-id').value = userId;
  document.getElementById('edit-user-nom').value = nom;
  document.getElementById('edit-user-prenom').value = prenom;
  document.getElementById('edit-user-matricule').value = matricule;
  document.getElementById('edit-user-email').value = email;
  document.getElementById('edit-user-telephone').value = telephone;
  if (agenceId) document.getElementById('edit-user-agence').value = agenceId;
  else document.getElementById('edit-user-agence').selectedIndex = 0;
  if (serviceId) document.getElementById('edit-user-service').value = serviceId;
  else document.getElementById('edit-user-service').value = '';
  document.getElementById('edit-user-password').value = '';
  loadResponsable(serviceId, responsableId);
  openModal('modal-edit-user');
}

function loadResponsable(serviceId, responsableId) {
  const wrap = document.getElementById('edit-user-responsable-wrap');
  const sel = document.getElementById('edit-user-responsable');
  if (!serviceId) {
    wrap.style.display = 'none';
    sel.value = '';
    return;
  }
  wrap.style.display = '';
  // Get responsable_id from the selected service option's data attribute
  const serviceSelect = document.getElementById('edit-user-service');
  const opt = serviceSelect.options[serviceSelect.selectedIndex];
  const currentRespId = opt ? opt.dataset.responsableId : '';
  if (responsableId !== undefined) {
    sel.value = responsableId;
  } else {
    sel.value = currentRespId || '';
  }
}

function affecterRole(userId, roleId, caisseId) {
  document.getElementById('aff-user-id').value = userId;
  document.getElementById('aff-role-id').value = roleId;
  if (caisseId) {
    document.getElementById('aff-caisse-id').value = caisseId;
  }
  toggleCaisseSelect();
  openModal('modal-affecter-role');
}

function toggleCaisseSelect() {
  const sel = document.getElementById('aff-role-id');
  const opt = sel.options[sel.selectedIndex];
  const isCaissier = opt && opt.dataset.nom === 'caissier';
  document.getElementById('caisse-field').style.display = isCaissier ? 'block' : 'none';
  if (!isCaissier) {
    document.getElementById('aff-caisse-id').value = '';
  }
}

/* ─── RADNEX CONFIG TOGGLE ───────────────────────────────────── */
function toggleRadnexFields() {
  const provider = document.getElementById('radnex-provider').value;
  document.querySelectorAll('.radnex-fields').forEach(el => el.style.display = 'none');
  const target = document.getElementById('radnex-' + provider);
  if (target) target.style.display = '';
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
