<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo isset($pageTitle) ? e($pageTitle) . ' | ' . APP_NAME : APP_NAME; ?></title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Ionicons -->
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap4-theme@1.0.0/dist/select2-bootstrap4.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Custom styles -->
    <style>
        .content-wrapper {
            min-height: calc(100vh - 57px - 60px);
        }
        .sidebar-dark-navy .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .brand-link .brand-text {
            font-weight: 700;
        }
        .nav-header {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .notification-count {
            position: absolute;
            top: 2px;
            right: 2px;
            font-size: 0.6rem;
            padding: 2px 5px;
        }
    </style>
    <?php if (isset($customCSS)): ?>
        <link rel="stylesheet" href="<?php echo APP_URL; ?>/css/<?php echo e($customCSS); ?>">
    <?php endif; ?>
</head>
<body class="hold-transition sidebar-dark-navy layout-fixed">
<div class="wrapper">

    <!-- Preloader -->
    <div class="preloader flex-column justify-content-center align-items-center bg-navy">
        <i class="fas fa-spinner fa-spin fa-2x text-white"></i>
    </div>

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <span class="nav-link text-muted">
                    <i class="fas fa-calendar-alt mr-1"></i> <?php echo date('d/m/Y'); ?>
                </span>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <!-- Notifications -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#" role="button">
                    <i class="far fa-bell"></i>
                    <?php
                    $unreadCount = 0;
                    if (class_exists('NotificationModel')) {
                        try {
                            $notifModel = new NotificationModel();
                            $unreadCount = $notifModel->countUnread(Auth::id());
                        } catch (Exception $e) {}
                    }
                    ?>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge badge-warning navbar-badge notification-count"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <span class="dropdown-item dropdown-header"><?php echo $unreadCount; ?> Notification(s)</span>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo APP_URL; ?>/notifications" class="dropdown-item">
                        <i class="fas fa-envelope mr-2 text-info"></i> Voir toutes les notifications
                    </a>
                </div>
            </li>

            <!-- User Account -->
            <li class="nav-item dropdown">
                <?php $currentUser = Auth::user(); ?>
                <a class="nav-link" data-toggle="dropdown" href="#" role="button">
                    <i class="far fa-user mr-1"></i>
                    <?php echo e($currentUser['username'] ?? 'Utilisateur'); ?>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <span class="dropdown-item dropdown-header">
                        <?php echo e($currentUser['role_name'] ?? ''); ?>
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

    <!-- Main Sidebar -->
    <aside class="main-sidebar sidebar-dark-navy elevation-4">
        <!-- Brand Logo -->
        <a href="<?php echo APP_URL; ?>/" class="brand-link navbar-navy">
            <i class="brand-icon fas fa-building ml-3"></i>
            <span class="brand-text font-weight-light ml-2">PayNovaRH</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- User panel -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image">
                    <?php if (!empty($currentUser['employee']['photo'])): ?>
                        <img src="<?php echo APP_URL; ?>/uploads/<?php echo e($currentUser['employee']['photo']); ?>" class="img-circle elevation-1" alt="Avatar" style="width:34px;height:34px;">
                    <?php else: ?>
                        <i class="fas fa-user-circle fa-2x text-light"></i>
                    <?php endif; ?>
                </div>
                <div class="info">
                    <a href="<?php echo APP_URL; ?>/profile" class="d-block text-light">
                        <?php echo e(($currentUser['employee']['first_name'] ?? '') . ' ' . ($currentUser['employee']['last_name'] ?? $currentUser['username'] ?? 'Utilisateur')); ?>
                    </a>
                </div>
            </div>

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                    <!-- Principal -->
                    <li class="nav-header">Principal</li>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/" class="nav-link<?php echo (isset($activeMenu) && $activeMenu === 'dashboard') ? ' active' : ''; ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Tableau de bord</p>
                        </a>
                    </li>

                    <!-- Gestion du personnel -->
                    <li class="nav-header">Gestion du personnel</li>
                    <?php if (Auth::hasAnyPermission('employees')): ?>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/employees" class="nav-link<?php echo (isset($activeMenu) && $activeMenu === 'employees') ? ' active' : ''; ?>">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Employés</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (Auth::hasAnyPermission('departments')): ?>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/departments" class="nav-link<?php echo (isset($activeMenu) && $activeMenu === 'departments') ? ' active' : ''; ?>">
                            <i class="nav-icon fas fa-building"></i>
                            <p>Départements</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (Auth::hasAnyPermission('positions')): ?>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/positions" class="nav-link<?php echo (isset($activeMenu) && $activeMenu === 'positions') ? ' active' : ''; ?>">
                            <i class="nav-icon fas fa-briefcase"></i>
                            <p>Postes</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (Auth::hasAnyPermission('contracts')): ?>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/contracts" class="nav-link<?php echo (isset($activeMenu) && $activeMenu === 'contracts') ? ' active' : ''; ?>">
                            <i class="nav-icon fas fa-file-contract"></i>
                            <p>Contrats</p>
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Congés & Présence -->
                    <li class="nav-header">Congés & Présence</li>
                    <?php if (Auth::hasAnyPermission('leaves')): ?>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/leaves" class="nav-link<?php echo (isset($activeMenu) && $activeMenu === 'leaves') ? ' active' : ''; ?>">
                            <i class="nav-icon fas fa-calendar-alt"></i>
                            <p>Congés</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (Auth::hasAnyPermission('attendance')): ?>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/attendance" class="nav-link<?php echo (isset($activeMenu) && $activeMenu === 'attendance') ? ' active' : ''; ?>">
                            <i class="nav-icon fas fa-clock"></i>
                            <p>Pointage</p>
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Paie -->
                    <li class="nav-header">Paie</li>
                    <?php if (Auth::hasAnyPermission('payroll')): ?>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/payroll" class="nav-link<?php echo (isset($activeMenu) && $activeMenu === 'payroll') ? ' active' : ''; ?>">
                            <i class="nav-icon fas fa-money-bill-wave"></i>
                            <p>Gestion de la paie</p>
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Recrutement -->
                    <li class="nav-header">Recrutement</li>
                    <?php if (Auth::hasAnyPermission('recruitment')): ?>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/recruitment" class="nav-link<?php echo (isset($activeMenu) && $activeMenu === 'recruitment') ? ' active' : ''; ?>">
                            <i class="nav-icon fas fa-bullhorn"></i>
                            <p>Offres d'emploi</p>
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Évaluations -->
                    <li class="nav-header">Évaluations</li>
                    <?php if (Auth::hasAnyPermission('evaluations')): ?>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/evaluations" class="nav-link<?php echo (isset($activeMenu) && $activeMenu === 'evaluations') ? ' active' : ''; ?>">
                            <i class="nav-icon fas fa-star"></i>
                            <p>Évaluations</p>
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Formations -->
                    <li class="nav-header">Formations</li>
                    <?php if (Auth::hasAnyPermission('training')): ?>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/training" class="nav-link<?php echo (isset($activeMenu) && $activeMenu === 'training') ? ' active' : ''; ?>">
                            <i class="nav-icon fas fa-graduation-cap"></i>
                            <p>Formations</p>
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Documents -->
                    <li class="nav-header">Documents</li>
                    <?php if (Auth::hasAnyPermission('documents')): ?>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/documents" class="nav-link<?php echo (isset($activeMenu) && $activeMenu === 'documents') ? ' active' : ''; ?>">
                            <i class="nav-icon fas fa-folder"></i>
                            <p>Documents</p>
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Administration -->
                    <li class="nav-header">Administration</li>
                    <?php if (Auth::isAdmin()): ?>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/roles" class="nav-link<?php echo (isset($activeMenu) && $activeMenu === 'roles') ? ' active' : ''; ?>">
                            <i class="nav-icon fas fa-lock"></i>
                            <p>Rôles & Permissions</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (Auth::hasAnyPermission('reports')): ?>
                    <li class="nav-item">
                        <a href="<?php echo APP_URL; ?>/reports" class="nav-link<?php echo (isset($activeMenu) && $activeMenu === 'reports') ? ' active' : ''; ?>">
                            <i class="nav-icon fas fa-chart-bar"></i>
                            <p>Rapports</p>
                        </a>
                    </li>
                    <?php endif; ?>

                </ul>
            </nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header (Page header + breadcrumb) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">
                            <?php echo isset($pageTitle) ? e($pageTitle) : ''; ?>
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/">Accueil</a></li>
                            <?php if (isset($breadcrumbs) && is_array($breadcrumbs)): ?>
                                <?php foreach ($breadcrumbs as $label => $url): ?>
                                    <?php if ($url): ?>
                                        <li class="breadcrumb-item"><a href="<?php echo APP_URL . e($url); ?>"><?php echo e($label); ?></a></li>
                                    <?php else: ?>
                                        <li class="breadcrumb-item active"><?php echo e($label); ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Flash messages -->
                <?php echo Flash::render(); ?>

                <!-- Page content -->
                <?php echo $content; ?>
            </div>
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    <!-- Main Footer -->
    <footer class="main-footer text-sm">
        <strong>&copy; 2024 <a href="<?php echo APP_URL; ?>/">PayNovaRH</a>.</strong>
        Tous droits réservés.
    </footer>

</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script>
// Hide preloader immediately when jQuery loads
$(window).on('load', function() { $('.preloader').fadeOut('slow'); });
setTimeout(function() { $('.preloader').fadeOut('slow'); }, 3000);
</script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/fr.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Custom scripts -->
<script src="<?php echo APP_URL; ?>/js/app.js"></script>

<script>
// DataTables defaults
$.extend(true, $.fn.dataTable.defaults, {
    language: {
        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr.json'
    },
    pageLength: 15,
    responsive: true,
    autoWidth: false,
    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
});

// SweetAlert2 defaults
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
});

// Initialize Select2
$(function () {
    $('.select2').each(function () {
        $(this).select2({
            theme: 'bootstrap4',
            language: 'fr',
            placeholder: $(this).data('placeholder') || 'Sélectionner...',
            allowClear: true
        });
    });
});

// Auto-dismiss flash alerts
$(function () {
    setTimeout(function () {
        $('.alert-dismissible').fadeOut('slow', function () {
            $(this).remove();
        });
    }, 5000);
});
</script>

<?php if (isset($customJS)): ?>
<script src="<?php echo APP_URL; ?>/js/<?php echo e($customJS); ?>"></script>
<?php endif; ?>
</body>
</html>