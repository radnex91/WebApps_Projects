<?php
$currentPage = 'notifications';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/notifier.php';

// --- POST : Marquer comme lu ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'marquer_lu') {
    csrf_verify();
    $nid = post_int('notification_id');
    if ($nid > 0) db_exec("UPDATE notifications SET lu=1 WHERE id=? AND utilisateur_id=?", [$nid, currentUser()['id']]);
    elseif (post_str('all') === '1') db_exec("UPDATE notifications SET lu=1 WHERE utilisateur_id=?", [currentUser()['id']]);
    header('Location: '.APP_URL.'/notifications.php'); exit;
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('notifications');

$notifications = db_select("SELECT * FROM notifications WHERE utilisateur_id=? ORDER BY date_notification DESC LIMIT 100", [currentUser()['id']]);
$nbNonLues = get_unread_count();
$typeIcon = ['info'=>'📌','alerte'=>'⚠️','critique'=>'🚨','succes'=>'✅'];
?>

<div class="page-header-row">
  <div><h2>🔔 Notifications</h2><p><?= $nbNonLues ?> non lues · <?= count($notifications) ?> au total</p></div>
  <?php if ($nbNonLues > 0): ?>
  <form method="POST"><input type="hidden" name="action" value="marquer_lu"><input type="hidden" name="all" value="1"><?= csrf_field() ?><button class="btn btn-sm btn-blue">✅ Tout marquer lu</button></form>
  <?php endif; ?>
</div>

<div class="card">
  <?php foreach ($notifications as $n): ?>
  <div style="padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;gap:12px;
      <?= !$n['lu']?'background:rgba(var(--accent-rgb),.05);border-left:3px solid var(--accent)':'' ?>">
    <div style="font-size:20px"><?= $typeIcon[$n['type']]??'📌' ?></div>
    <div style="flex:1">
      <div style="font-weight:<?= $n['lu']?'400':'700' ?>">
        <?php if ($n['lien']): ?><a href="<?= h($n['lien']) ?>" style="color:var(--text)"><?= h($n['titre']) ?></a>
        <?php else: ?><?= h($n['titre']) ?><?php endif; ?>
      </div>
      <?php if ($n['message']): ?><div style="font-size:12px;color:var(--text2);margin-top:2px"><?= h($n['message']) ?></div><?php endif; ?>
      <div style="font-size:10px;color:var(--text3);margin-top:4px"><?= fmt_date($n['date_notification'], true) ?></div>
    </div>
    <?php if (!$n['lu']): ?>
    <form method="POST"><input type="hidden" name="action" value="marquer_lu"><input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" title="Marquer lu">✓</button></form>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if (empty($notifications)): ?>
  <div style="text-align:center;padding:48px;color:var(--text3)">
    <div style="font-size:40px;margin-bottom:12px">🔔</div>
    <div>Aucune notification.</div>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
