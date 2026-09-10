<?php
$pageTitle = 'Sauvegarde & Restauration';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('sauvegarde');

$db = getDB();
$backupDir = __DIR__ . '/../backups/';
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

// Créer une sauvegarde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'backup') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $filename = 'danay_ledger_' . date('Ymd_His') . '.sql';
        $filepath = $backupDir . $filename;

        $dumpCommand = "mysqldump -h" . DB_HOST . " -u" . DB_USER . " " . (DB_PASS ? "-p" . DB_PASS . " " : "") . DB_NAME . " > \"$filepath\"";
        exec($dumpCommand, $output, $returnCode);

        if ($returnCode === 0 && file_exists($filepath)) {
            $size = filesize($filepath);
            $stmt = $db->prepare("INSERT INTO sauvegardes (nom_fichier, taille, type, created_by) VALUES (?, ?, 'manuel', ?)");
            $stmt->execute([$filename, $size, $_SESSION['user_id']]);
            addAuditLog('backup', 'sauvegarde', $db->lastInsertId(), [], ['filename' => $filename]);
            setFlash('success', 'Sauvegarde créée avec succès.');
        } else {
            // Fallback: PHP-based backup
            $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $sql = "-- DanayLedger Backup " . date('Y-m-d H:i:s') . "\n\n";
            foreach ($tables as $table) {
                $create = $db->query("SHOW CREATE TABLE `$table`")->fetch();
                $sql .= "-- Table: $table\n" . $create['Create Table'] . ";\n\n";
                $rows = $db->query("SELECT * FROM `$table`")->fetchAll();
                foreach ($rows as $row) {
                    $values = array_map(function($v) use ($db) { return $v === null ? 'NULL' : $db->quote($v); }, array_values($row));
                    $sql .= "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
                }
                $sql .= "\n";
            }
            file_put_contents($filepath, $sql);
            $size = filesize($filepath);
            $stmt = $db->prepare("INSERT INTO sauvegardes (nom_fichier, taille, type, created_by) VALUES (?, ?, 'manuel', ?)");
            $stmt->execute([$filename, $size, $_SESSION['user_id']]);
            addAuditLog('backup', 'sauvegarde', $db->lastInsertId(), [], ['filename' => $filename]);
            setFlash('success', 'Sauvegarde créée (mode PHP).');
        }
    }
    header('Location: index.php');
    exit;
}

// Restaurer une sauvegarde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore' && hasPermission('sauvegarde_restore')) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM sauvegardes WHERE id = ?");
        $stmt->execute([$id]);
        $backup = $stmt->fetch();
        if ($backup && file_exists($backupDir . $backup['nom_fichier'])) {
            $sql = file_get_contents($backupDir . $backup['nom_fichier']);
            try {
                $db->exec($sql);
                addAuditLog('restore', 'sauvegarde', $id);
                setFlash('success', 'Restauration effectuée avec succès.');
            } catch (Exception $e) {
                setFlash('error', 'Erreur de restauration : ' . $e->getMessage());
            }
        } else {
            setFlash('error', 'Fichier de sauvegarde introuvable.');
        }
    }
    header('Location: index.php');
    exit;
}

// Supprimer une sauvegarde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM sauvegardes WHERE id = ?");
        $stmt->execute([$id]);
        $backup = $stmt->fetch();
        if ($backup) {
            if (file_exists($backupDir . $backup['nom_fichier'])) unlink($backupDir . $backup['nom_fichier']);
            $db->prepare("DELETE FROM sauvegardes WHERE id = ?")->execute([$id]);
            addAuditLog('delete', 'sauvegarde', $id);
            setFlash('success', 'Sauvegarde supprimée.');
        }
    }
    header('Location: index.php');
    exit;
}

$sauvegardes = $db->query("SELECT s.*, u.full_name as created_by_name FROM sauvegardes s LEFT JOIN users u ON s.created_by=u.id ORDER BY s.created_at DESC")->fetchAll();
?>
<div class="main-content" id="main-content" role="main">
    <header class="main-header" role="banner"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button><span class="mb-0 fw-bold"><?php echo e($pageTitle); ?></span></div><div class="header-right"><div class="dropdown"><button class="notif-btn" aria-label="Notifications" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" role="button" tabindex="0" aria-label="Menu utilisateur" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-cloud-arrow-down-fill me-2"></i>Sauvegarde & Restauration</h1></div>
            <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="backup"><button type="submit" class="btn btn-primary"><i class="bi bi-download me-1"></i>Créer une sauvegarde</button></form>
        </div>

        <div class="card"><div class="card-header"><i class="bi bi-clock-history me-2"></i>Historique des sauvegardes</div><div class="table-container">
            <table class="table" aria-label="Sauvegardes">
                <thead><tr><th>Date</th><th>Fichier</th><th>Taille</th><th>Type</th><th>Créé par</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($sauvegardes as $s): ?>
                <tr>
                    <td><?php echo formatDate($s['created_at']); ?></td>
                    <td><small><?php echo e($s['nom_fichier']); ?></small></td>
                    <td><?php echo number_format($s['taille'] / 1024, 1); ?> KB</td>
                    <td><span class="badge <?php echo $s['type']==='auto'?'bg-info':'bg-primary'; ?>"><?php echo $s['type']==='auto'?'Auto':'Manuel'; ?></span></td>
                    <td><?php echo e($s['created_by_name'] ?? 'Système'); ?></td>
                    <td><div class="action-btns">
                        <a href="<?php echo APP_URL; ?>/backups/<?php echo e($s['nom_fichier']); ?>" class="btn btn-sm btn-outline-primary" download><i class="bi bi-download"></i></a>
                        <?php if (hasPermission('sauvegarde_restore')): ?>
                        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?php echo $s['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-warning" data-confirm="ATTENTION: La restauration remplacera toutes les données actuelles. Continuer ?"><i class="bi bi-arrow-counterclockwise"></i></button></form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $s['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Supprimer" data-confirm="Supprimer cette sauvegarde ?"><i class="bi bi-trash"></i></button></form>
                    </div></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($sauvegardes)): ?><tr><td colspan="6" class="text-center text-muted py-4">Aucune sauvegarde</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>