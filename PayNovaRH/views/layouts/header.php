<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="<?php echo APP_URL; ?>/" class="nav-link">Accueil</a>
        </li>
    </ul>

    <!-- Search -->
    <form class="form-inline ml-3" action="<?php echo APP_URL; ?>/search" method="get">
        <div class="input-group input-group-sm">
            <input class="form-control form-control-navbar" type="search" placeholder="Rechercher..." name="q" aria-label="Rechercher">
            <div class="input-group-append">
                <button class="btn btn-navbar" type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </form>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
        <!-- Notifications dropdown -->
        <li class="nav-item dropdown">
            <?php
            $unreadCount = 0;
            if (class_exists('NotificationModel')) {
                try {
                    $notifModel = new NotificationModel();
                    $unreadCount = $notifModel->countUnread(Auth::id());
                } catch (Exception $e) {}
            }
            ?>
            <a class="nav-link" data-toggle="dropdown" href="#" role="button">
                <i class="far fa-bell"></i>
                <?php if ($unreadCount > 0): ?>
                    <span class="badge badge-warning navbar-badge notification-count"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <span class="dropdown-item dropdown-header">
                    <?php echo $unreadCount; ?> Notification(s)
                </span>
                <div class="dropdown-divider"></div>
                <a href="<?php echo APP_URL; ?>/notifications" class="dropdown-item dropdown-footer">
                    <i class="fas fa-envelope mr-2 text-info"></i> Voir toutes les notifications
                </a>
            </div>
        </li>

        <!-- User Account dropdown -->
        <?php $currentUser = Auth::user(); ?>
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#" role="button">
                <?php if (!empty($currentUser['employee']['photo'])): ?>
                    <img src="<?php echo APP_URL; ?>/uploads/<?php echo e($currentUser['employee']['photo']); ?>"
                         class="img-circle elevation-1 mr-1"
                         alt="Avatar"
                         style="width:25px;height:25px;">
                <?php else: ?>
                    <i class="fas fa-user-circle mr-1"></i>
                <?php endif; ?>
                <span class="d-none d-sm-inline">
                    <?php echo e($currentUser['employee']['first_name'] ?? $currentUser['username'] ?? 'Utilisateur'); ?>
                </span>
            </a>
            <div class="dropdown-menu dropdown-menu-right">
                <span class="dropdown-item dropdown-header bg-light">
                    <small class="text-muted"><?php echo e($currentUser['role_name'] ?? ''); ?></small>
                </span>
                <div class="dropdown-divider"></div>
                <a href="<?php echo APP_URL; ?>/profile" class="dropdown-item">
                    <i class="fas fa-user mr-2 text-primary"></i> Mon profil
                </a>
                <a href="<?php echo APP_URL; ?>/change-password" class="dropdown-item">
                    <i class="fas fa-key mr-2 text-warning"></i> Changer le mot de passe
                </a>
                <div class="dropdown-divider"></div>
                <a href="<?php echo APP_URL; ?>/logout" class="dropdown-item text-danger">
                    <i class="fas fa-sign-out-alt mr-2"></i> Déconnexion
                </a>
            </div>
        </li>
    </ul>
</nav>