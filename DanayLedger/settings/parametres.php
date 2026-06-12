<?php
$pageTitle = 'Paramètres Système';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('settings_params');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    foreach ($_POST['params'] ?? [] as $id => $valeur) {
        $id = (int)$id;
        $valeur = cleanInput($valeur);
        $db->prepare("UPDATE parametres SET valeur = ?, updated_by = ? WHERE id = ?")->execute([$valeur, $_SESSION['user_id'], $id]);
    }
    addAuditLog('update', 'parametre', 0);
    setFlash('success', 'Paramètres mis à jour.');
    header('Location: parametres.php'); exit;
}

$params = $db->query("SELECT p.*, u.full_name as updated_by_name FROM parametres p LEFT JOIN users u ON p.updated_by=u.id ORDER BY p.cle")->fetchAll();
?>
<div class="main-content" id="main-content" role="main">
    <header class="main-header" role="banner"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button><span class="mb-0 fw-bold"><?php echo e($pageTitle); ?></span></div><div class="header-right"><div class="dropdown"><button class="notif-btn" aria-label="Notifications" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" role="button" tabindex="0" aria-label="Menu utilisateur" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-sliders me-2"></i>Paramètres Système</h1></div></div>

        <form method="POST">
            <?php echo csrfField(); ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-check me-2"></i>Configuration</span>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Sauvegarder</button>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table" aria-label="Paramètres système">
                            <thead><tr><th>Clé</th><th>Description</th><th>Valeur</th><th>Modifié par</th><th>Dernière MAJ</th></tr></thead>
                            <tbody>
                            <?php foreach ($params as $p): ?>
                            <tr>
                                <td><code><?php echo e($p['cle']); ?></code></td>
                                <td><small><?php echo e($p['description'] ?? '-'); ?></small></td>
                                <td><input type="text" name="params[<?php echo $p['id']; ?>]" value="<?php echo e($p['valeur'] ?? ''); ?>" class="form-control form-control-sm" style="max-width:300px"></td>
                                <td><small><?php echo e($p['updated_by_name'] ?? '-'); ?></small></td>
                                <td><small class="text-muted"><?php echo formatDate($p['updated_at']); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>