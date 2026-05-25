<?php
// modules/sauvegarde/index.php
require_once '../../includes/config.php';
requireLogin(); if(!isSuperAdmin()){flash('Accès Super Admin uniquement.','danger');redirect(BASE_URL.'index.php');}
$pageTitle = 'Sauvegarde & Restauration';
$bkDir = UPLOAD_DIR.'backups/';
if(!is_dir($bkDir)) @mkdir($bkDir, 0755, true);

// ── SAUVEGARDE ──────────────────────────────────────────────
if (isset($_POST['backup'])) {
    $fn  = 'gesttrans_backup_'.date('Ymd_His').'.sql';
    $fp  = $bkDir.$fn;
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $sql = "-- GestTrans Pro Backup | ".date('Y-m-d H:i:s')." | ".DB_NAME."\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach($tables as $t) {
        $cr=$pdo->query("SHOW CREATE TABLE `$t`")->fetch(); $sql.="DROP TABLE IF EXISTS `$t`;\n".$cr['Create Table'].";\n\n";
        $rows=$pdo->query("SELECT * FROM `$t`")->fetchAll();
        if(!empty($rows)){
            $cols='`'.implode('`,`',array_keys($rows[0])).'`';
            $vals=array_map(fn($r)=>'('.implode(',',array_map(fn($v)=>$v===null?'NULL':"'".$pdo->quote(str_replace("'","\\'",(string)$v))."'",$r)).')',$rows);
            $sql.="INSERT INTO `$t` ($cols) VALUES\n".implode(",\n",$vals).";\n\n";
        }
    }
    $sql.="SET FOREIGN_KEY_CHECKS=1;\n";
    file_put_contents($fp,$sql);
    logAction($pdo,'backup','sauvegarde',$fn);
    flash("Sauvegarde $fn créée.");
    if(isset($_POST['dl'])){header('Content-Type: application/sql');header('Content-Disposition: attachment; filename="'.$fn.'"');echo $sql;exit();}
    redirect(BASE_URL.'modules/sauvegarde/');
}

// ── TÉLÉCHARGEMENT ──────────────────────────────────────────
if(isset($_GET['dl'])){
    $f=$bkDir.basename($_GET['dl']);
    if(file_exists($f)&&strpos($f,'gesttrans_backup_')!==false){header('Content-Type: application/sql');header('Content-Disposition: attachment; filename="'.basename($f).'"');readfile($f);exit();}
}

// ── SUPPRIMER ───────────────────────────────────────────────
if(isset($_GET['del'])){
    $f=$bkDir.basename($_GET['del']);
    if(file_exists($f)) @unlink($f);
    flash('Sauvegarde supprimée.','warning');
    redirect(BASE_URL.'modules/sauvegarde/');
}

// ── RESTAURATION ────────────────────────────────────────────
if(isset($_POST['restore'])&&!empty($_FILES['sql_file']['tmp_name'])){
    $ext=strtolower(pathinfo($_FILES['sql_file']['name'],PATHINFO_EXTENSION));
    if($ext!=='sql'){flash('Fichier .sql requis.','danger');}
    else{
        try{
            $sql=file_get_contents($_FILES['sql_file']['tmp_name']);
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            $stmts=array_filter(array_map('trim',explode(";\n",$sql)));
            $c=0;
            foreach($stmts as $s){if(!empty($s)&&!str_starts_with(trim($s),'--')){$pdo->exec($s);$c++;}}
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            logAction($pdo,'restore','sauvegarde',$_FILES['sql_file']['name']);
            flash("Restauration effectuée — $c instructions.");
        }catch(PDOException $e){flash('Erreur restauration: '.$e->getMessage(),'danger');}
    }
    redirect(BASE_URL.'modules/sauvegarde/');
}

$backups=glob($bkDir.'gesttrans_backup_*.sql'); rsort($backups);
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Sauvegarde</div>

<div class="flash flash-warning" style="border-radius:var(--radius);margin-bottom:16px;">
  <i class="fas fa-exclamation-triangle"></i>
  <strong>Zone sensible</strong> — Réservée aux Super Administrateurs. La restauration écrase toutes les données existantes.
</div>

<div class="grid-2" style="margin-bottom:20px;">
<div class="card">
  <div class="card-header"><h3><i class="fas fa-download"></i> Créer une sauvegarde</h3></div>
  <div class="card-body">
    <p style="font-size:13px;color:var(--text2);margin-bottom:16px;">Génère un export SQL complet de la base de données <strong><?= DB_NAME ?></strong>.</p>
    <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;">
      <button type="submit" name="backup" class="btn btn-primary btn-lg"><i class="fas fa-database"></i> Créer la sauvegarde</button>
      <button type="submit" name="backup" value="1" onclick="this.form.dl.value='1'" class="btn btn-success btn-lg"><i class="fas fa-file-download"></i> Créer & Télécharger</button>
      <input type="hidden" name="dl" value="0">
    </form>
  </div>
</div>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-upload"></i> Restaurer depuis un fichier</h3></div>
  <div class="card-body">
    <div class="flash flash-danger" style="border-radius:var(--radius);margin-bottom:12px;font-size:12px;"><i class="fas fa-radiation-alt"></i> <strong>Attention :</strong> Écrase toutes les données actuelles.</div>
    <form method="POST" enctype="multipart/form-data">
      <div class="fg" style="margin-bottom:10px;"><label class="flbl">Fichier SQL</label><input type="file" name="sql_file" class="fc" accept=".sql" required></div>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;font-size:13px;"><input type="checkbox" id="ck" required><label for="ck">Je confirme vouloir écraser les données existantes</label></div>
      <button type="submit" name="restore" class="btn btn-danger btn-lg"><i class="fas fa-undo"></i> Restaurer la base</button>
    </form>
  </div>
</div>
</div>

<div class="card">
  <div class="card-header"><h3><i class="fas fa-archive"></i> Sauvegardes disponibles (<?= count($backups) ?>)</h3></div>
  <div class="card-body" style="padding:0;"><div class="table-wrap">
  <table>
    <thead><tr><th>Nom du fichier</th><th>Date</th><th>Taille</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($backups as $bk): $bn=basename($bk); ?>
      <tr>
        <td><i class="fas fa-database" style="color:var(--primary);margin-right:6px;"></i><code><?= h($bn) ?></code></td>
        <td><?= date('d/m/Y H:i',filemtime($bk)) ?></td>
        <td><?= round(filesize($bk)/1024,1) ?> Ko</td>
        <td>
          <div style="display:flex;gap:4px;">
            <a href="?dl=<?= urlencode($bn) ?>" class="btn btn-xs btn-primary"><i class="fas fa-download"></i> Télécharger</a>
            <a href="?del=<?= urlencode($bn) ?>" class="btn btn-xs btn-danger" onclick="return confirm('Supprimer cette sauvegarde ?')"><i class="fas fa-trash"></i></a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($backups)): ?><tr><td colspan="4" class="t-empty"><i class="fas fa-database"></i>Aucune sauvegarde locale</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div></div>
</div>
<?php include '../../includes/footer.php'; ?>
