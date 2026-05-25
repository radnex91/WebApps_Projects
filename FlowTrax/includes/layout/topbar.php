<div class="topbar">
    <div class="topbar-left">
        <h2><?= e($page_title ?? 'Dashboard') ?></h2>
    </div>
    <div class="topbar-right">
        <div class="topbar-date">
            <span class="emoji-animate">📅</span>
            <?= date('d/m/Y') ?>
        </div>
        <div class="topbar-notifications">
            <span class="emoji-animate">🔔</span>
            <span class="notification-dot"></span>
        </div>
        <div class="topbar-user">
            <span><?= e($_SESSION['user_nom'] ?? '') ?></span>
            <a href="index.php?page=logout" class="btn btn-sm btn-ghost">
                <span class="emoji-animate">🚪</span>
            </a>
        </div>
    </div>
</div>
