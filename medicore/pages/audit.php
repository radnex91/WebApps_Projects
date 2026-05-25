<?php
$currentPage = 'audit';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('audit');

// --- Donnees ---
$page = max(1, get_int('p', 1));
$limit = 50;
$offset = ($page - 1) * $limit;
$patient_id = get_int('patient_id');
$user_id = get_int('user_id');
$date_from = get_str('from') ?: date('Y-m-d', strtotime('-7 days'));
$date_to   = get_str('to') ?: date('Y-m-d');

$where = "WHERE a.date_acces >= ? AND a.date_acces <= ?";
$params = [$date_from.' 00:00:00', $date_to.' 23:59:59'];
if ($patient_id > 0) { $where .= " AND a.patient_id=?"; $params[] = $patient_id; }
if ($user_id > 0) { $where .= " AND a.utilisateur_id=?"; $params[] = $user_id; }

$acces = db_select("SELECT a.*, CONCAT(u.prenom,' ',u.nom) AS user_nom, u.role AS user_role,
    CONCAT(p.prenom,' ',p.nom) AS patient_nom, p.numero AS patient_num
    FROM audit_acces a
    LEFT JOIN utilisateurs u ON u.id=a.utilisateur_id
    LEFT JOIN patients p ON p.id=a.patient_id
    $where ORDER BY a.date_acces DESC LIMIT $limit OFFSET $offset", $params);

$total = (int)db_scalar("SELECT COUNT(*) FROM audit_acces a $where", $params);
$pages = ceil($total / $limit);

$typeIcon = ['consultation'=>'👁️','modification'=>'✏️','creation'=>'➕','suppression'=>'🗑️','export'=>'⬇️'];
$typeColor = ['consultation'=>'badge-blue','modification'=>'badge-yellow','creation'=>'badge-green','suppression'=>'badge-red','export'=>'badge-blue'];

// Utilisateurs pour filtre
$users = db_select("SELECT id, CONCAT(prenom,' ',nom) AS n, role FROM utilisateurs WHERE statut='actif' ORDER BY nom");
?>

<div class="page-header-row">
  <div><h2>🔍 Audit d'accès aux dossiers</h2><p><?= $total ?> accès enregistrés · <?= fmt_date($date_from) ?> → <?= fmt_date($date_to) ?></p></div>
</div>

<!-- Filtres -->
<div class="card" style="margin-bottom:16px">
  <form method="GET" style="padding:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <label>Du :</label><input type="date" name="from" value="<?= h($date_from) ?>" style="max-width:150px">
    <label>au :</label><input type="date" name="to" value="<?= h($date_to) ?>" style="max-width:150px">
    <select name="user_id" style="max-width:200px"><option value="">Tous les utilisateurs</option>
      <?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $user_id==$u['id']?'selected':'' ?>><?= h($u['n'].' ('.$u['role'].')') ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-sm btn-blue">Filtrer</button>
    <?php if ($user_id > 0 || $patient_id > 0): ?><a href="audit.php" class="btn btn-sm btn-ghost">✕ Reset</a><?php endif; ?>
  </form>
</div>

<!-- Stats rapides -->
<div class="stats-grid mb-24">
  <div class="stat-card"><div class="stat-icon blue">👁️</div><div class="stat-value"><?= $total ?></div><div class="stat-label">Accès (periode)</div></div>
  <?php
  $nbConsult = (int)db_scalar("SELECT COUNT(*) FROM audit_acces a WHERE a.type_acces='consultation' AND a.date_acces >=? AND a.date_acces<=?".($user_id>0?' AND a.utilisateur_id='.$user_id:'')."".($patient_id>0?' AND a.patient_id='.$patient_id:''), [$date_from.' 00:00:00',$date_to.' 23:59:59']);
  $nbModif   = (int)db_scalar("SELECT COUNT(*) FROM audit_acces a WHERE a.type_acces IN ('modification','creation','suppression') AND a.date_acces >=? AND a.date_acces<=?".($user_id>0?' AND a.utilisateur_id='.$user_id:'')."".($patient_id>0?' AND a.patient_id='.$patient_id:''), [$date_from.' 00:00:00',$date_to.' 23:59:59']);
  ?>
  <div class="stat-card"><div class="stat-icon green">✅</div><div class="stat-value"><?= $nbConsult ?></div><div class="stat-label">Consultations</div></div>
  <div class="stat-card"><div class="stat-icon yellow">✏️</div><div class="stat-value"><?= $nbModif ?></div><div class="stat-label">Modifications</div></div>
  <div class="stat-card"><div class="stat-icon">📄</div><div class="stat-value"><?= $pages ?></div><div class="stat-label">Pages</div></div>
</div>

<!-- Tableau -->
<div class="card"><table>
  <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Patient</th><th>Entite</th><th>Adresse IP</th></tr></thead><tbody>
  <?php foreach ($acces as $a): ?>
  <tr><td style="font-size:12px"><?= fmt_date($a['date_acces'], true) ?></td>
    <td><?= h($a['user_nom']??'Systeme') ?><div style="font-size:10px;color:var(--text3)"><?= h($a['user_role']??'') ?></div></td>
    <td><span class="badge <?= $typeColor[$a['type_acces']]??'badge-gray' ?>"><?= $typeIcon[$a['type_acces']]??'' ?> <?= h($a['type_acces']) ?></span></td>
    <td><?php if($a['patient_id']): ?><a href="dossiers.php?patient_id=<?= (int)$a['patient_id'] ?>"><?= h($a['patient_nom']) ?></a><?php else: ?><span style="color:var(--text3)">-</span><?php endif; ?></td>
    <td style="font-size:11px;color:var(--text3)"><?= h(($a['entite']??'').($a['entite_id']?' #'.$a['entite_id']:'')) ?></td>
    <td style="font-size:10px;color:var(--text3)"><?= h($a['adresse_ip']??'') ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($acces)): ?>
  <tr><td colspan="6" style="text-align:center;padding:48px;color:var(--text3)">Aucun acces enregistre pour cette periode.</td></tr>
  <?php endif; ?></tbody></table>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div class="pill-tabs mb-16" style="margin-top:16px">
  <?php for ($i=1;$i<=min($pages,20);$i++): ?>
  <a href="?p=<?= $i ?>&from=<?= h($date_from) ?>&to=<?= h($date_to) ?><?= $user_id?'&user_id='.$user_id:'' ?><?= $patient_id?'&patient_id='.$patient_id:'' ?>" class="pill-tab <?= $page==$i?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($pages>20): ?><span style="color:var(--text3)">... <?= $pages ?></span><?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
