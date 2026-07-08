<div class="page-header">
    <div>
        <h1>Notifications</h1>
        <div class="page-header-subtitle">Toutes vos notifications</div>
    </div>
</div>
<div class="card-content">
    <div class="card-content-header">
        <h5><i class="bi bi-bell" style="margin-right:6px;color:var(--md-secondary)"></i> Liste des notifications</h5>
    </div>
    <div class="card-content-body" style="padding:0">
        <?php foreach ($notifications as $n): ?>
        <div style="display:flex;align-items:flex-start;gap:12px;padding:14px 20px;border-bottom:1px solid #eee;<?= !$n->is_read ? 'background:#f0f7ff' : '' ?>">
            <i class="bi <?= $n->is_read ? 'bi-envelope-open text-muted' : 'bi-envelope-fill' ?>" style="margin-top:2px;color:<?= $n->is_read ? '#9e9e9e' : 'var(--md-primary)' ?>"></i>
            <div style="flex:1">
                <div style="font-size:14px"><?= htmlspecialchars($n->message) ?></div>
                <div style="font-size:11px;color:#999;margin-top:4px"><?= $n->created_at ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($notifications)): ?>
        <div style="padding:40px 20px;text-align:center;color:#999">
            <i class="bi bi-bell-slash" style="font-size:32px;display:block;margin-bottom:8px"></i>
            Aucune notification
        </div>
        <?php endif; ?>
    </div>
</div>