<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1><i class="bi bi-bell"></i> Notifications</h1>
</div>
<div class="list-group">
    <?php foreach ($notifications as $n): ?>
    <div class="list-group-item <?= $n->is_read ? '' : 'list-group-item-info' ?>">
        <p class="mb-1"><?= htmlspecialchars($n->message) ?></p>
        <small class="text-muted"><?= $n->created_at ?></small>
    </div>
    <?php endforeach; ?>
    <?php if (empty($notifications)): ?>
    <p class="text-muted">Aucune notification</p>
    <?php endif; ?>
</div>
