<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('audit');
$pageTitle = 'Journal d\'Audit';

$db = getDB();

$module_filter = $_GET['module'] ?? '';
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin   = $_GET['date_fin'] ?? date('Y-m-d');
$user_filter = $_GET['user'] ?? '';

$where = ['ja.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)'];
$params = [$date_debut, $date_fin];

if ($module_filter) { $where[] = 'ja.module = ?'; $params[] = $module_filter; }
if ($user_filter)   { $where[] = 'ja.utilisateur_id = ?'; $params[] = $user_filter; }

$whereStr = 'WHERE ' . implode(' AND ', $where);

$logs = $db->prepare("SELECT ja.*, CONCAT(u.nom,' ',u.prenom) as user_nom FROM journal_audit ja LEFT JOIN utilisateurs u ON ja.utilisateur_id=u.id $whereStr ORDER BY ja.created_at DESC LIMIT 200");
$logs->execute($params);
$logs = $logs->fetchAll();

$modules = $db->query("SELECT DISTINCT module FROM journal_audit ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
$utilisateurs = $db->query("SELECT id, CONCAT(prenom,' ',nom) as nom FROM utilisateurs ORDER BY nom")->fetchAll();

$actionIcons = ['login'=>'<i class="fa-solid fa-right-to-bracket"></i>','logout'=>'<i class="fa-solid fa-right-from-bracket"></i>','create'=>'<i class="fa-solid fa-plus"></i>','update'=>'<i class="fa-solid fa-pen"></i>','delete'=>'<i class="fa-solid fa-xmark"></i>','ouverture_caisse'=>'<i class="fa-solid fa-play"></i>','fermeture_caisse'=>'<i class="fa-solid fa-stop"></i>','saisie_operation'=>'<i class="fa-solid fa-circle-dot"></i>','valider_engagement'=>'<i class="fa-solid fa-check"></i>','creer_engagement'=>'<i class="fa-solid fa-circle-plus"></i>'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header"><h1>Journal d'Audit</h1><p>Traçabilité complète de toutes les actions dans le système</p></div>

<!-- Filters -->
<div class="card mb-20">
  <div class="card-body" style="padding:16px">
    <form method="get" class="d-flex gap-8 align-center" style="flex-wrap:wrap">
      <div class="form-group mb-0">
        <label class="form-label">Du</label>
        <input type="date" name="date_debut" class="form-control" value="<?= $date_debut ?>" style="width:150px">
      </div>
      <div class="form-group mb-0">
        <label class="form-label">Au</label>
        <input type="date" name="date_fin" class="form-control" value="<?= $date_fin ?>" style="width:150px">
      </div>
      <div class="form-group mb-0">
        <label class="form-label">Module</label>
        <select name="module" class="form-control" style="width:160px">
          <option value="">Tous</option>
          <?php foreach($modules as $m): ?>
          <option value="<?= $m ?>" <?= $module_filter===$m?'selected':'' ?>><?= ucfirst($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group mb-0">
        <label class="form-label">Utilisateur</label>
        <select name="user" class="form-control" style="width:160px">
          <option value="">Tous</option>
          <?php foreach($utilisateurs as $u): ?>
          <option value="<?= $u['id'] ?>" <?= $user_filter==$u['id']?'selected':'' ?>><?= sanitize($u['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="margin-top:18px">
        <button type="submit" class="btn btn-primary">Filtrer</button>
        <a href="index.php" class="btn btn-ghost">Réinitialiser</a>
      </div>
    </form>
  </div>
</div>

<!-- Log table -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><?= count($logs) ?> entrée(s) d'audit</span>
    <input type="text" id="search-audit" class="form-control" placeholder="Rechercher..." style="width:200px;margin-left:auto">
  </div>
  <div class="table-wrap">
    <table id="tbl-audit">
      <thead>
        <tr><th>Date/Heure</th><th>Utilisateur</th><th>Action</th><th>Module</th><th>Table</th><th>ID</th><th>IP</th></tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:32px">Aucune entrée dans la période sélectionnée</td></tr>
        <?php else: foreach($logs as $l): ?>
        <tr>
          <td style="white-space:nowrap;font-size:12.5px"><?= date('d/m/Y H:i:s', strtotime($l['created_at'])) ?></td>
          <td><?= sanitize($l['user_nom']??'Système') ?></td>
          <td>
            <span style="display:inline-flex;align-items:center;gap:5px">
              <span style="width:20px;height:20px;background:var(--surface2);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px"><?= $actionIcons[$l['action']] ?? '•' ?></span>
              <?= sanitize($l['action']) ?>
            </span>
          </td>
          <td><span class="badge badge-info" style="font-size:11px"><?= sanitize($l['module']) ?></span></td>
          <td><?= sanitize($l['table_cible']??'—') ?></td>
          <td><?= $l['enregistrement_id'] ?? '—' ?></td>
          <td style="font-size:12px;color:var(--text3)"><?= sanitize($l['ip_address']??'—') ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>tableSearch('search-audit', 'tbl-audit');</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
