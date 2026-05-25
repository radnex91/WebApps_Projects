<?php // modules/notifications.php
require_once '../includes/config.php'; requireLogin();
$pageTitle='Notifications'; $uid=$_SESSION['user_id'];

// AJAX: count endpoint
if (isset($_GET['ajax']) && $_GET['ajax']==='count') {
    header('Content-Type: application/json');
    echo json_encode(['count' => countUnreadNotifs($pdo, $uid)]);
    exit;
}

if(isset($_GET['read_all'])){$pdo->prepare("UPDATE notifications SET lue=1 WHERE user_id=?")->execute([$uid]);flash('Toutes les notifications lues.');redirect(BASE_URL.'modules/notifications.php');}
if(isset($_GET['read'])){$pdo->prepare("UPDATE notifications SET lue=1 WHERE id=? AND user_id=?")->execute([$_GET['read'],$uid]);redirect(BASE_URL.'modules/notifications.php');}
$notifs=$pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100");$notifs->execute([$uid]);$notifs=$notifs->fetchAll();
$unread=count(array_filter($notifs,fn($n)=>!$n['lue']));
include '../includes/header.php'; ?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Notifications</div>
<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-bell"></i> Notifications <?php if($unread): ?><span class="nav-badge"><?= $unread ?></span><?php endif; ?></h3>
    <?php if($unread): ?><a href="?read_all=1" class="btn btn-secondary btn-sm"><i class="fas fa-check-double"></i> Tout marquer lu</a><?php endif; ?>
  </div>
  <div class="card-body" style="padding:0;">
    <?php foreach($notifs as $n): ?>
    <div style="display:flex;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border);<?= !$n['lue']?'background:var(--primary-bg);':'' ?>">
      <div style="width:8px;height:8px;border-radius:50%;background:<?= $n['lue']?'var(--border2)':'var(--primary)' ?>;margin-top:6px;flex-shrink:0;"></div>
      <div style="flex:1;">
        <div style="font-weight:<?= $n['lue']?'400':'600' ?>;font-size:13px;"><?= sanitize($n['titre']) ?></div>
        <div style="font-size:12px;color:var(--text2);margin-top:2px;"><?= sanitize($n['message']??'') ?></div>
        <div style="font-size:11px;color:var(--text3);margin-top:3px;"><?= timeAgo($n['created_at']) ?></div>
      </div>
      <?php if(!$n['lue']): ?><a href="?read=<?= $n['id'] ?>" class="btn btn-xs btn-ghost" title="Marquer lu"><i class="fas fa-check"></i></a><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if(empty($notifs)): ?><div class="empty"><i class="fas fa-bell-slash"></i><p>Aucune notification</p></div><?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
