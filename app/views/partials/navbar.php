<?php
use App\Helpers\Auth;
$userName = Auth::name();
$userRole = Auth::role();
$roleLabel = match($userRole) {
    'admin' => 'Administrateur',
    'technicien' => 'Technicien',
    'client' => 'Client',
    default => ''
};
$uri = $_SERVER['REQUEST_URI'] ?? '';
$pageTitle = 'Tableau de bord';
if (strpos($uri, '/tickets') !== false) $pageTitle = 'Tickets';
elseif (strpos($uri, '/kb') !== false) $pageTitle = 'Base de connaissances';
elseif (strpos($uri, '/admin/users') !== false) $pageTitle = 'Utilisateurs';
elseif (strpos($uri, '/admin/categories') !== false) $pageTitle = 'Catégories';
elseif (strpos($uri, '/admin/priorities') !== false) $pageTitle = 'Priorités';
elseif (strpos($uri, '/admin/sla') !== false) $pageTitle = 'SLA';
elseif (strpos($uri, '/admin/reports') !== false) $pageTitle = 'Rapports';
elseif (strpos($uri, '/admin/archive') !== false) $pageTitle = 'Archives';
elseif (strpos($uri, '/admin/notifications') !== false) $pageTitle = 'Notifications';
elseif (strpos($uri, '/admin/settings') !== false) $pageTitle = 'Paramètres';
elseif (strpos($uri, '/profile') !== false) $pageTitle = 'Profil';
?>
<div class="topbar">
    <div class="topbar-left">
        <button id="sidebarToggle" class="mobile-toggle" type="button" aria-label="Menu">
            <i class="bi bi-list" style="font-size:20px"></i>
        </button>
        <div>
            <div class="topbar-page-title"><?= $pageTitle ?></div>
        </div>
    </div>
    <div class="topbar-right">
        <div class="topbar-user" id="userDropdownToggle">
            <div class="topbar-avatar">
                <?= strtoupper(substr($userName, 0, 1)) ?>
            </div>
            <div style="display:none" class="d-md-block">
                <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                <div class="topbar-user-role"><?= $roleLabel ?></div>
            </div>
        </div>
        <div id="userDropdown" class="dropdown-material">
            <a class="dropdown-material-item" href="/gestion-support/profile">
                <i class="bi bi-person"></i> Profil
            </a>
            <div class="dropdown-material-divider"></div>
            <a class="dropdown-material-item" href="/gestion-support/logout">
                <i class="bi bi-box-arrow-right"></i> Déconnexion
            </a>
        </div>
    </div>
</div>
