<div id="toast-container">
    <?php if (isset($_SESSION['flash']) && !empty($_SESSION['flash'])): ?>
        <?php foreach ($_SESSION['flash'] as $flash): ?>
            <?php if (is_array($flash) && isset($flash['type'], $flash['message'])): ?>
                <div class="toast-ps5 toast-<?= $flash['type'] ?>">
                    <div class="toast-ps5-icon">
                        <?php if ($flash['type'] === 'success'): ?>✓
                        <?php elseif ($flash['type'] === 'danger' || $flash['type'] === 'error'): ?>✕
                        <?php else: ?>ℹ
                        <?php endif; ?>
                    </div>
                    <div class="toast-ps5-message"><?= htmlspecialchars($flash['message']) ?></div>
                    <button class="toast-ps5-close" onclick="this.parentElement.remove()">✕</button>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toasts = document.querySelectorAll('.toast-ps5');
    toasts.forEach(function(toast) {
        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100px)';
            setTimeout(function() { toast.remove(); }, 400);
        }, 4000);
    });
});
</script>
