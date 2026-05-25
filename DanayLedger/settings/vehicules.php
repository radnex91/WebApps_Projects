<?php
$pageTitle = 'Véhicules';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('settings_vehicules');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['form_action'] ?? '';
    if ($action === 'create') {
        $imm = cleanInput($_POST['immatriculation']??''); $marque = cleanInput($_POST['marque']??'');
        $modele = cleanInput($_POST['modele']??''); $annee = (int)($_POST['annee']??0)?:null;
        $type_v = cleanInput($_POST['type_vehicule']??''); $agence_id = (int)($_POST['agence_id']??0)?:null;
        if (empty($imm)) { setFlash('error','Immatriculation obligatoire.'); }
        else { try { $db->prepare("INSERT INTO vehicules (immatriculation,marque,modele,annee,type_vehicule,agence_id) VALUES (?,?,?,?,?,?)")->execute([$imm,$marque,$modele,$annee,$type_v,$agence_id]); addAuditLog('create','vehicule',$db->lastInsertId()); setFlash('success','Véhicule créé.'); } catch(Exception $e) { setFlash('error','Erreur : '.$e->getMessage()); } }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id']??0); $imm = cleanInput($_POST['immatriculation']??''); $marque = cleanInput($_POST['marque']??'');
        $modele = cleanInput($_POST['modele']??''); $annee = (int)($_POST['annee']??0)?:null;
        $type_v = cleanInput($_POST['type_vehicule']??''); $agence_id = (int)($_POST['agence_id']??0)?:null; $statut = $_POST['statut']??'actif';
        try { $db->prepare("UPDATE vehicules SET immatriculation=?,marque=?,modele=?,annee=?,type_vehicule=?,agence_id=?,statut=? WHERE id=?")->execute([$imm,$marque,$modele,$annee,$type_v,$agence_id,$statut,$id]); addAuditLog('update','vehicule',$id); setFlash('success','Véhicule modifié.'); } catch(Exception $e) { setFlash('error','Erreur : '.$e->getMessage()); }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id']??0); $db->prepare("DELETE FROM vehicules WHERE id=?")->execute([$id]); addAuditLog('delete','vehicule',$id); setFlash('success','Véhicule supprimé.');
    }
    header('Location: vehicules.php'); exit;
}

$vehicules = $db->query("SELECT v.*, a.nomagence as agence_nom FROM vehicules v LEFT JOIN agence a ON v.agence_id=a.id ORDER BY v.immatriculation")->fetchAll();
$agences = getAgences($db);
$statutLabels = ['actif'=>'Actif','inactif'=>'Inactif','en_maintenance'=>'En maintenance'];
$statutBadges = ['actif'=>'bg-success','inactif'=>'bg-secondary','en_maintenance'=>'bg-warning text-dark'];
?>
<div class="main-content">
    <header class="main-header"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button><h6 class="mb-0 fw-bold"><?php echo e($pageTitle); ?></h6></div><div class="header-right"><div class="dropdown"><button class="notif-btn" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-truck me-2"></i>Véhicules</h1></div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Nouveau véhicule</button>
        </div>
        <div class="card"><div class="table-container">
            <table class="table">
                <thead><tr><th>Immatriculation</th><th>Marque/Modèle</th><th>Année</th><th>Type</th><th>Agence</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($vehicules as $v): ?>
                <tr>
                    <td><code class="fw-bold"><?php echo e($v['immatriculation']); ?></code></td>
                    <td><?php echo e(trim(($v['marque']??'').' '.($v['modele']??'')) ?: '-'); ?></td>
                    <td><?php echo $v['annee'] ?? '-'; ?></td>
                    <td><small><?php echo e($v['type_vehicule'] ?? '-'); ?></small></td>
                    <td><small><?php echo e($v['agence_nom'] ?? '-'); ?></small></td>
                    <td><span class="badge <?php echo $statutBadges[$v['statut']]??'bg-secondary'; ?>"><?php echo e($statutLabels[$v['statut']]??$v['statut']); ?></span></td>
                    <td><div class="action-btns">
                        <button class="btn btn-sm btn-outline-warning" onclick='editVeh(<?php echo htmlspecialchars(json_encode($v)); ?>)'><i class="bi bi-pencil"></i></button>
                        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?php echo $v['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Supprimer ?"><i class="bi bi-trash"></i></button></form>
                    </div></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Nouveau véhicule</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="form_action" value="create">
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Immatriculation *</label><input type="text" name="immatriculation" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Marque</label><input type="text" name="marque" class="form-control"></div>
        <div class="mb-3"><label class="form-label">Modèle</label><input type="text" name="modele" class="form-control"></div>
        <div class="mb-3"><label class="form-label">Année</label><input type="number" name="annee" class="form-control" min="1990" max="2030"></div>
        <div class="mb-3"><label class="form-label">Type</label><input type="text" name="type_vehicule" class="form-control" placeholder="Camion, Fourgon..."></div>
        <div class="mb-3"><label class="form-label">Agence</label><select name="agence_id" class="form-select"><option value="">-- Aucune --</option><?php foreach($agences as $a): ?><option value="<?php echo $a['id']; ?>"><?php echo e($a['nomagence']); ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Créer</button></div>
    </form>
</div></div></div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Modifier véhicule</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="form_action" value="update"><input type="hidden" name="id" id="edit_id">
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Immatriculation *</label><input type="text" name="immatriculation" id="edit_imm" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Marque</label><input type="text" name="marque" id="edit_marque" class="form-control"></div>
        <div class="mb-3"><label class="form-label">Modèle</label><input type="text" name="modele" id="edit_modele" class="form-control"></div>
        <div class="mb-3"><label class="form-label">Année</label><input type="number" name="annee" id="edit_annee" class="form-control" min="1990" max="2030"></div>
        <div class="mb-3"><label class="form-label">Type</label><input type="text" name="type_vehicule" id="edit_type_v" class="form-control"></div>
        <div class="mb-3"><label class="form-label">Agence</label><select name="agence_id" id="edit_agence" class="form-select"><option value="">-- Aucune --</option><?php foreach($agences as $a): ?><option value="<?php echo $a['id']; ?>"><?php echo e($a['nomagence']); ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">Statut</label><select name="statut" id="edit_statut" class="form-select"><option value="actif">Actif</option><option value="inactif">Inactif</option><option value="en_maintenance">En maintenance</option></select></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Modifier</button></div>
    </form>
</div></div></div>

<script>
function editVeh(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_imm').value = data.immatriculation;
    document.getElementById('edit_marque').value = data.marque || '';
    document.getElementById('edit_modele').value = data.modele || '';
    document.getElementById('edit_annee').value = data.annee || '';
    document.getElementById('edit_type_v').value = data.type_vehicule || '';
    document.getElementById('edit_agence').value = data.agence_id || '';
    document.getElementById('edit_statut').value = data.statut;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>