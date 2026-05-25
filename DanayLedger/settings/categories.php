<?php
$pageTitle = 'Catégories';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('settings_categories');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['form_action'] ?? '';
    if ($action === 'create') {
        $code = cleanInput($_POST['code'] ?? ''); $nom = cleanInput($_POST['nom'] ?? '');
        $type = $_POST['type'] ?? ''; $description = cleanInput($_POST['description'] ?? '');
        if (empty($code) || empty($nom) || empty($type)) { setFlash('error', 'Code, nom et type obligatoires.'); }
        else { try { $db->prepare("INSERT INTO categories (code,nom,type,description) VALUES (?,?,?,?)")->execute([$code,$nom,$type,$description]); addAuditLog('create','categorie',$db->lastInsertId()); setFlash('success','Catégorie créée.'); } catch(Exception $e) { setFlash('error','Erreur : '.$e->getMessage()); } }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id']??0); $code = cleanInput($_POST['code']??''); $nom = cleanInput($_POST['nom']??'');
        $type = $_POST['type']??''; $description = cleanInput($_POST['description']??''); $statut = $_POST['statut']??'actif';
        try { $db->prepare("UPDATE categories SET code=?,nom=?,type=?,description=?,statut=? WHERE id=?")->execute([$code,$nom,$type,$description,$statut,$id]); addAuditLog('update','categorie',$id); setFlash('success','Catégorie modifiée.'); } catch(Exception $e) { setFlash('error','Erreur : '.$e->getMessage()); }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id']??0); $db->prepare("DELETE FROM categories WHERE id=?")->execute([$id]); addAuditLog('delete','categorie',$id); setFlash('success','Catégorie supprimée.');
    }
    header('Location: categories.php'); exit;
}

$filterType = $_GET['type'] ?? '';
$where = $filterType ? "WHERE type = ?" : "";
$params = $filterType ? [$filterType] : [];
$cats = $db->prepare("SELECT * FROM categories $where ORDER BY type, nom")->execute($params) ? $db->prepare("SELECT * FROM categories $where ORDER BY type, nom") : $db->query("SELECT * FROM categories ORDER BY type, nom");
if ($filterType) { $cats = $db->prepare("SELECT * FROM categories WHERE type=? ORDER BY nom"); $cats->execute([$filterType]); } else { $cats = $db->query("SELECT * FROM categories ORDER BY type, nom"); }
?>
<div class="main-content">
    <header class="main-header"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button><h6 class="mb-0 fw-bold"><?php echo e($pageTitle); ?></h6></div><div class="header-right"><div class="dropdown"><button class="notif-btn" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-tags me-2"></i>Catégories</h1></div>
            <div class="d-flex gap-2">
                <select class="form-select form-select-sm" style="width:auto" onchange="location.href='categories.php?type='+this.value">
                    <option value="">Tous les types</option><option value="recette" <?php echo $filterType==='recette'?'selected':''; ?>>Recettes</option><option value="depense" <?php echo $filterType==='depense'?'selected':''; ?>>Dépenses</option>
                </select>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Nouvelle</button>
            </div>
        </div>
        <div class="card"><div class="table-container">
            <table class="table">
                <thead><tr><th>Code</th><th>Nom</th><th>Type</th><th>Description</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($cats->fetchAll() as $c): ?>
                <tr>
                    <td><code><?php echo e($c['code']); ?></code></td>
                    <td class="fw-600"><?php echo e($c['nom']); ?></td>
                    <td><span class="badge <?php echo $c['type']==='recette'?'bg-success':'bg-danger'; ?>"><?php echo e($c['type']==='recette'?'Recette':'Dépense'); ?></span></td>
                    <td><small><?php echo e($c['description'] ?? '-'); ?></small></td>
                    <td><span class="badge <?php echo $c['statut']==='actif'?'bg-success':'bg-secondary'; ?>"><?php echo e($c['statut']); ?></span></td>
                    <td><div class="action-btns">
                        <button class="btn btn-sm btn-outline-warning" onclick='editCat(<?php echo htmlspecialchars(json_encode($c)); ?>)'><i class="bi bi-pencil"></i></button>
                        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?php echo $c['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Supprimer ?"><i class="bi bi-trash"></i></button></form>
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
    <div class="modal-header"><h5 class="modal-title">Nouvelle catégorie</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="form_action" value="create">
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Code *</label><input type="text" name="code" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Type *</label><select name="type" class="form-select" required><option value="recette">Recette</option><option value="depense">Dépense</option></select></div>
        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Créer</button></div>
    </form>
</div></div></div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Modifier catégorie</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="form_action" value="update"><input type="hidden" name="id" id="edit_id">
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Code *</label><input type="text" name="code" id="edit_code" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Nom *</label><input type="text" name="nom" id="edit_nom" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Type *</label><select name="type" id="edit_type" class="form-select" required><option value="recette">Recette</option><option value="depense">Dépense</option></select></div>
        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="edit_description" class="form-control" rows="2"></textarea></div>
        <div class="mb-3"><label class="form-label">Statut</label><select name="statut" id="edit_statut" class="form-select"><option value="actif">Actif</option><option value="inactif">Inactif</option></select></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Modifier</button></div>
    </form>
</div></div></div>

<script>
function editCat(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_code').value = data.code;
    document.getElementById('edit_nom').value = data.nom;
    document.getElementById('edit_type').value = data.type;
    document.getElementById('edit_description').value = data.description || '';
    document.getElementById('edit_statut').value = data.statut;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>