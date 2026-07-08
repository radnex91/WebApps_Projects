<?php
use App\Helpers\Auth;
$role = Auth::role();
$uri = $_SERVER['REQUEST_URI'] ?? '';
function isActive($paths) {
    global $uri;
    foreach ((array)$paths as $p) {
        if (strpos($uri, $p) !== false) return ' active';
    }
    return '';
}
?>
<div id="sidebar" class="sidebar">
    <a class="sidebar-brand" href="/gestion-support/<?= $role ?>/dashboard">
        <span class="sidebar-brand-icon">🎫</span>
        Gestion Support
    </a>
    <div class="sidebar-nav">
        <div class="sidebar-heading">Navigation</div>
        <a class="nav-link<?= isActive('/dashboard') ?>" href="/gestion-support/<?= $role ?>/dashboard">
            <span class="nav-emoji">📊</span> Tableau de bord
        </a>
        <a class="nav-link<?= isActive('/tickets') ?>" href="/gestion-support/tickets">
            <span class="nav-emoji">🎟️</span> Tickets
        </a>
        <a class="nav-link<?= isActive('/kb') ?>" href="/gestion-support/kb">
            <span class="nav-emoji">📚</span> Base de connaissances
        </a>
        <?php if ($role === 'admin'): ?>
        <div class="sidebar-section-divider"></div>
        <div class="sidebar-heading">Administration</div>
        <a class="nav-link<?= isActive('/admin/users') ?>" href="/gestion-support/admin/users">
            <span class="nav-emoji">👥</span> Utilisateurs
        </a>
        <a class="nav-link<?= isActive('/admin/categories') ?>" href="/gestion-support/admin/categories">
            <span class="nav-emoji">🏷️</span> Catégories
        </a>
        <a class="nav-link<?= isActive('/admin/priorities') ?>" href="/gestion-support/admin/priorities">
            <span class="nav-emoji">🚩</span> Priorités
        </a>
        <a class="nav-link<?= isActive('/admin/sla') ?>" href="/gestion-support/admin/sla">
            <span class="nav-emoji">⏱️</span> SLA
        </a>
        <a class="nav-link<?= isActive('/admin/reports') ?>" href="/gestion-support/admin/reports">
            <span class="nav-emoji">📈</span> Rapports
        </a>
        <a class="nav-link<?= isActive('/admin/archive') ?>" href="/gestion-support/admin/archive">
            <span class="nav-emoji">🗄️</span> Archives
        </a>
        <a class="nav-link<?= isActive('/admin/notifications') ?>" href="/gestion-support/admin/notifications">
            <span class="nav-emoji">🔔</span> Notifications
        </a>
        <a class="nav-link<?= isActive('/admin/settings') ?>" href="/gestion-support/admin/settings">
            <span class="nav-emoji">⚙️</span> Paramètres
        </a>
        <?php endif; ?>
    </div>
</div>
