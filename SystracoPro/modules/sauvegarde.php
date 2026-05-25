<?php
// modules/sauvegarde.php — Sauvegarde & Restauration
require_once '../includes/config.php';
requireLogin(); if(!isSuperAdmin()){flash('Accès réservé aux Super Administrateurs.','danger');redirect(BASE_URL.'index.php');}
$pageTitle = 'Sauvegarde & Restauration';

$backupDir = UPLOAD_DIR . 'backups/';
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

// ── SAUVEGARDE ────────────────────────────────────────────────
if (isset($_POST['backup'])) {
    $filename = 'backup_transport_'.date('Ymd_His').'.sql';
    $filepath = $backupDir.$filename;

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $sql = "-- TransportManager Database Backup\n-- Date: ".date('Y-m-d H:i:s')."\n-- Database: ".DB_NAME."\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // Structure
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
        $sql .= "DROP TABLE IF EXISTS `$table`;\n".$create['Create Table'].";\n\n";
        // Données
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll();
        if (!empty($rows)) {
            $cols = '`'.implode('`,`', array_keys($rows[0])).'`';
            $sql .= "INSERT INTO `$table` ($cols) VALUES\n";
            $vals = [];
            foreach ($rows as $row) {
                $escaped = array_map(fn($v) => $v===null?'NULL':$pdo->quote($v), $row);
                $vals[] = '('.implode(',',$escaped).')';
            }
            $sql .= implode(",\n",$vals).";\n\n";
        }
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    file_put_contents($filepath, $sql);
    logAction($pdo,'backup','sauvegarde',"Sauvegarde: $filename");
    flash("Sauvegarde créée : $filename");

    // Télécharger directement
    if (isset($_POST['download_backup'])) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        readfile($filepath);
        exit();
    }
    redirect(BASE_URL.'modules/sauvegarde.php');
}

// ── RESTAURATION ──────────────────────────────────────────────
if (isset($_POST['restore']) && !empty($_FILES['sql_file']['tmp_name'])) {
    $tmpFile = $_FILES['sql_file']['tmp_name'];
    $origName = $_FILES['sql_file']['name'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if ($ext !== 'sql') {
        flash('Fichier invalide. Seuls les fichiers .sql sont acceptés.', 'danger');
    } else {
        try {
            $sql = file_get_contents($tmpFile);
            // Exécuter par blocs
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            $statements = array_filter(array_map('trim', explode(";\n", $sql)));
            $count = 0;
            foreach ($statements as $stmt) {
                if (!empty($stmt) && !str_starts_with(trim($stmt),'--')) {
                    $pdo->exec($stmt);
                    $count++;
                }
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            logAction($pdo,'restore','sauvegarde',"Restauration depuis: $origName");
            flash("Restauration effectuée — $count instructions exécutées.");
        } catch (PDOException $e) {
            flash('Erreur lors de la restauration: '.$e->getMessage(), 'danger');
        }
    }
    redirect(BASE_URL.'modules/sauvegarde.php');
}

// ── SUPPRIMER BACKUP ──────────────────────────────────────────
if (isset($_GET['del'])) {
    $f = $backupDir.basename($_GET['del']);
    if (file_exists($f) && str_contains($f,'backup_')) { unlink($f); flash('Sauvegarde supprimée.','warning'); }
    redirect(BASE_URL.'modules/sauvegarde.php');
}

// Liste sauvegardes
$backups = glob($backupDir.'backup_*.sql');
rsort($backups);

include '../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Sauvegarde & Restauration</div>

<div class="flash flash-warning" style="border-radius:var(--radius);margin-bottom:16px;">
  <i class="fas fa-exclamation-triangle"></i>
  <strong>Zone sensible</strong> — Les opérations de restauration écrasent les données existantes. Effectuez toujours une sauvegarde avant de restaurer.
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

<!-- CRÉER SAUVEGARDE -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-download"></i> Créer une sauvegarde</h3></div>
  <div class="card-body">
    <p style="font-size:13px;color:var(--text2);margin-bottom:16px;">Génère un fichier SQL complet de la base de données. Ce fichier peut être utilisé pour restaurer le système ou migrer vers un autre serveur.</p>
    <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;">
      <?= csrfField() ?>
      <button type="submit" name="backup" value="1" class="btn btn-primary btn-lg">
        <i class="fas fa-database"></i> Créer la sauvegarde
      </button>
      <button type="submit" name="backup" value="1" class="btn btn-success btn-lg" onclick="this.form.elements['download_backup'].value='1'">
        <i class="fas fa-file-download"></i> Sauvegarder & Télécharger
      </button>
      <input type="hidden" name="download_backup" value="0">
    </form>
  </div>
</div>

<!-- RESTAURER -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-upload"></i> Restaurer depuis un fichier</h3></div>
  <div class="card-body">
    <p style="font-size:13px;color:var(--danger);margin-bottom:16px;"><strong>⚠ Attention :</strong> Cette opération remplacera toutes les données actuelles par celles du fichier de sauvegarde.</p>
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <div class="fg" style="margin-bottom:12px;">
        <label class="flbl">Fichier SQL de sauvegarde</label>
        <input type="file" name="sql_file" class="fc" accept=".sql" required>
        <div class="fhint">Fichiers .sql uniquement (exportés par ce système)</div>
      </div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
        <input type="checkbox" id="confirm-restore" required>
        <label for="confirm-restore" style="font-size:12px;cursor:pointer;">Je confirme vouloir écraser les données actuelles</label>
      </div>
      <button type="submit" name="restore" value="1" class="btn btn-danger btn-lg">
        <i class="fas fa-undo"></i> Restaurer la base de données
      </button>
    </form>
  </div>
</div>
</div>

<!-- LISTE SAUVEGARDES -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-archive"></i> Sauvegardes disponibles (<?= count($backups) ?>)</h3></div>
  <div class="card-body" style="padding:0;">
    <table data-no-filter>
      <thead><tr><th>Nom du fichier</th><th>Date</th><th>Taille</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($backups as $backup):
          $bname = basename($backup);
          $bsize = filesize($backup);
          $bdate = filemtime($backup);
        ?>
        <tr>
          <td><i class="fas fa-database" style="color:var(--primary);margin-right:6px;"></i><strong><?= sanitize($bname) ?></strong></td>
          <td><?= date('d/m/Y H:i', $bdate) ?></td>
          <td><?= round($bsize/1024, 1) ?> Ko</td>
          <td>
            <div style="display:flex;gap:4px;">
              <a href="?download_file=<?= urlencode($bname) ?>" class="btn btn-xs btn-primary" onclick="downloadBackup('<?= urlencode($bname) ?>')"><i class="fas fa-download"></i> Télécharger</a>
              <a href="?del=<?= urlencode($bname) ?>" class="btn btn-xs btn-danger" onclick="return confirm('Supprimer cette sauvegarde ?')"><i class="fas fa-trash"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($backups)): ?>
        <tr><td colspan="4" class="t-empty"><i class="fas fa-database"></i>Aucune sauvegarde disponible</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
// Télécharger fichier backup existant
if (isset($_GET['download_file'])) {
    $f = $backupDir.basename($_GET['download_file']);
    if (file_exists($f)) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="'.basename($f).'"');
        readfile($f); exit();
    }
}
?>

<?php include '../includes/footer.php'; ?>
