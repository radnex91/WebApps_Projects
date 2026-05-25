<?php
$title = 'Tableau de bord';
$activeMenu = 'dashboard';
$breadcrumbs = ['Tableau de bord' => false];
?>

<!-- KPI Cards Row -->
<div class="row">
    <!-- Total Employees -->
    <div class="col-lg-3 col-md-6 col-sm-6 col-12">
        <div class="info-box bg-navy">
            <span class="info-box-icon"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Employés</span>
                <span class="info-box-number">
                    <?php echo number_format($totalEmployees ?? 0); ?>
                </span>
                <?php if (isset($employeesChange)): ?>
                <span class="info-box-text text-sm">
                    <i class="fas fa-arrow-<?php echo $employeesChange >= 0 ? 'up' : 'down'; ?> mr-1"></i>
                    <?php echo abs($employeesChange); ?> ce mois
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pending Leaves -->
    <div class="col-lg-3 col-md-6 col-sm-6 col-12">
        <div class="info-box bg-warning">
            <span class="info-box-icon"><i class="fas fa-calendar-alt"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Congés en attente</span>
                <span class="info-box-number">
                    <?php echo number_format($pendingLeaves ?? 0); ?>
                </span>
                <?php if (($pendingLeaves ?? 0) > 0): ?>
                <span class="info-box-text text-sm">
                    <a href="<?php echo APP_URL; ?>/leaves?status=pending" class="text-white">
                        <i class="fas fa-external-link-alt mr-1"></i> Voir les demandes
                    </a>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Present Today -->
    <div class="col-lg-3 col-md-6 col-sm-6 col-12">
        <div class="info-box bg-success">
            <span class="info-box-icon"><i class="fas fa-clock"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Présents aujourd'hui</span>
                <span class="info-box-number">
                    <?php echo number_format($presentToday ?? 0); ?>
                </span>
                <?php if (isset($totalEmployees) && $totalEmployees > 0): ?>
                <span class="info-box-text text-sm">
                    <?php echo round(($presentToday / $totalEmployees) * 100); ?>% de présence
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Departments -->
    <div class="col-lg-3 col-md-6 col-sm-6 col-12">
        <div class="info-box bg-info">
            <span class="info-box-icon"><i class="fas fa-building"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Départements</span>
                <span class="info-box-number">
                    <?php echo number_format($totalDepartments ?? 0); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row">
    <!-- Employees by Department - Pie Chart -->
    <div class="col-lg-6">
        <div class="card card-navy card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-pie mr-1"></i> Employés par département
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height: 300px;">
                    <canvas id="chartDepartment"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Gender Distribution - Doughnut Chart -->
    <div class="col-lg-6">
        <div class="card card-navy card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-venus-mars mr-1"></i> Répartition par genre
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height: 300px;">
                    <canvas id="chartGender"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Row -->
<div class="row">
    <!-- Recent Leave Requests -->
    <div class="col-lg-7">
        <div class="card card-navy card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-check mr-1"></i> Dernières demandes de congé
                </h3>
                <div class="card-tools">
                    <a href="<?php echo APP_URL; ?>/leaves" class="btn btn-tool" title="Voir tout">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($recentLeaves) && is_array($recentLeaves) && count($recentLeaves) > 0): ?>
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Type</th>
                            <th>Début</th>
                            <th>Fin</th>
                            <th>Statut</th>
                            <?php if (Auth::hasPermission('leaves', 'approve')): ?>
                            <th class="text-center">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($recentLeaves, 0, 5) as $leave): ?>
                        <tr>
                            <td>
                                <a href="<?php echo APP_URL; ?>/employees/<?php echo $leave['employee_id'] ?? ''; ?>">
                                    <?php echo e(($leave['first_name'] ?? '') . ' ' . ($leave['last_name'] ?? '')); ?>
                                </a>
                            </td>
                            <td><?php echo e($leave['leave_type'] ?? $leave['type_name'] ?? ''); ?></td>
                            <td><?php echo formatDate($leave['start_date'] ?? ''); ?></td>
                            <td><?php echo formatDate($leave['end_date'] ?? ''); ?></td>
                            <td><?php echo getStatusBadge($leave['status'] ?? ''); ?></td>
                            <?php if (Auth::hasPermission('leaves', 'approve')): ?>
                            <td class="text-center">
                                <?php if (($leave['status'] ?? '') === 'pending'): ?>
                                <a href="<?php echo APP_URL; ?>/leaves/<?php echo $leave['id'] ?? ''; ?>/approve"
                                   class="btn btn-success btn-xs mx-1" title="Approuver"
                                   onclick="return confirm('Approuver cette demande ?')">
                                    <i class="fas fa-check"></i>
                                </a>
                                <a href="<?php echo APP_URL; ?>/leaves/<?php echo $leave['id'] ?? ''; ?>/reject"
                                   class="btn btn-danger btn-xs mx-1" title="Rejeter"
                                   onclick="return confirm('Rejeter cette demande ?')">
                                    <i class="fas fa-times"></i>
                                </a>
                                <?php else: ?>
                                <span class="text-muted small">--</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                    <p>Aucune demande de congé récente.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Notifications -->
    <div class="col-lg-5">
        <div class="card card-navy card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-bell mr-1"></i> Notifications récentes
                </h3>
                <div class="card-tools">
                    <a href="<?php echo APP_URL; ?>/notifications" class="btn btn-tool" title="Voir tout">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($recentNotifications) && is_array($recentNotifications) && count($recentNotifications) > 0): ?>
                <ul class="products-list product-list-in-card pl-2 pr-2">
                    <?php foreach (array_slice($recentNotifications, 0, 7) as $notif): ?>
                    <li class="item<?php echo empty($notif['read_at']) ? ' font-weight-bold' : ''; ?>">
                        <div class="product-img py-2">
                            <?php
                            $iconClass = 'fas fa-info-circle text-info';
                            if (strpos($notif['type'] ?? '', 'leave') !== false) {
                                $iconClass = 'fas fa-calendar-alt text-warning';
                            } elseif (strpos($notif['type'] ?? '', 'attendance') !== false) {
                                $iconClass = 'fas fa-clock text-success';
                            } elseif (strpos($notif['type'] ?? '', 'payroll') !== false) {
                                $iconClass = 'fas fa-money-bill-wave text-navy';
                            } elseif (strpos($notif['type'] ?? '', 'alert') !== false) {
                                $iconClass = 'fas fa-exclamation-triangle text-danger';
                            }
                            ?>
                            <i class="<?php echo $iconClass; ?> fa-lg"></i>
                        </div>
                        <div class="product-info">
                            <a href="<?php echo APP_URL . ($notif['link'] ?? '#'); ?>" class="product-title">
                                <?php echo e($notif['title'] ?? $notif['message'] ?? ''); ?>
                            </a>
                            <span class="product-description text-muted text-sm">
                                <?php echo e($notif['message'] ?? ''); ?>
                                <br>
                                <small class="text-muted">
                                    <i class="far fa-clock mr-1"></i>
                                    <?php echo timeAgo($notif['created_at'] ?? ''); ?>
                                </small>
                            </span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-bell-slash fa-2x mb-2"></i>
                    <p>Aucune notification récente.</p>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($recentNotifications) && count($recentNotifications) > 0): ?>
            <div class="card-footer text-center">
                <a href="<?php echo APP_URL; ?>/notifications" class="text-sm text-navy">
                    Voir toutes les notifications <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js Scripts -->
<script>
$(function () {
    // Color palette
    var palette = [
        '#001f3f', '#007bff', '#28a745', '#ffc107', '#dc3545',
        '#6c757d', '#17a2b8', '#6610f2', '#fd7e14', '#20c997'
    ];

    // Employees by Department - Pie Chart
    var deptData = <?php echo json_encode($departmentChart ?? ['labels' => [], 'data' => []]); ?>;
    var deptCtx = document.getElementById('chartDepartment').getContext('2d');
    new Chart(deptCtx, {
        type: 'pie',
        data: {
            labels: deptData.labels,
            datasets: [{
                data: deptData.data,
                backgroundColor: palette.slice(0, deptData.labels.length),
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            var total = context.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                            var pct = total > 0 ? Math.round((context.parsed / total) * 100) : 0;
                            return context.label + ' : ' + context.parsed + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });

    // Gender Distribution - Doughnut Chart
    var genderData = <?php echo json_encode($genderChart ?? ['labels' => ['Homme', 'Femme'], 'data' => [0, 0]]); ?>;
    var genderCtx = document.getElementById('chartGender').getContext('2d');
    new Chart(genderCtx, {
        type: 'doughnut',
        data: {
            labels: genderData.labels,
            datasets: [{
                data: genderData.data,
                backgroundColor: ['#001f3f', '#e83e8c'],
                borderColor: '#fff',
                borderWidth: 2,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            var total = context.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                            var pct = total > 0 ? Math.round((context.parsed / total) * 100) : 0;
                            return context.label + ' : ' + context.parsed + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });
});
</script>