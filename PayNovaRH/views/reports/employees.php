<?php
$title = 'Rapport Employés';
$activeMenu = 'reports';
$breadcrumbs = ['Rapports' => '/reports', 'Employés' => null];
?>

<div class="row mb-3">
    <div class="col-12 text-right">
        <a href="<?php echo APP_URL; ?>/reports/export/employees" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-file-csv mr-1"></i> Exporter CSV
        </a>
    </div>
</div>

<!-- Charts -->
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-pie mr-1"></i> Répartition par département</h3>
            </div>
            <div class="card-body">
                <canvas id="chart-department" height="300"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-pie mr-1"></i> Répartition par genre</h3>
            </div>
            <div class="card-body">
                <canvas id="chart-gender" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Employee Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-users mr-1"></i> Liste complète des employés</h3>
            </div>
            <div class="card-body">
                <table id="employees-table" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Email</th>
                            <th>Département</th>
                            <th>Poste</th>
                            <th>Contrat</th>
                            <th>Salaire</th>
                            <th>Statut</th>
                            <th>Date embauche</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($employees)): ?>
                            <?php foreach ($employees as $emp): ?>
                                <tr>
                                    <td><?php echo e($emp['last_name'] ?? ''); ?></td>
                                    <td><?php echo e($emp['first_name'] ?? ''); ?></td>
                                    <td><?php echo e($emp['email'] ?? ''); ?></td>
                                    <td><?php echo e($emp['department_name'] ?? '-'); ?></td>
                                    <td><?php echo e($emp['position_name'] ?? '-'); ?></td>
                                    <td><?php echo !empty($emp['contract_type']) ? getStatusBadge($emp['contract_type']) : '-'; ?></td>
                                    <td><?php echo !empty($emp['contract_salary']) ? formatMoney($emp['contract_salary']) : '-'; ?></td>
                                    <td><?php echo getStatusBadge($emp['status'] ?? ''); ?></td>
                                    <td><?php echo formatDate($emp['hire_date'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">Aucun employé trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    $('#employees-table').DataTable();

    // Department pie chart
    <?php if (!empty($byDept)): ?>
    var deptLabels = [<?php foreach ($byDept as $d) echo "'" . e($d['name']) . "',"; ?>];
    var deptData = [<?php foreach ($byDept as $d) echo (int)$d['count'] . ","; ?>];
    var deptColors = ['#007bff','#28a745','#ffc107','#dc3545','#6c757d','#17a2b8','#6610f2','#fd7e14','#20c997','#e83e8c'];
    new Chart(document.getElementById('chart-department'), {
        type: 'pie',
        data: {
            labels: deptLabels,
            datasets: [{ data: deptData, backgroundColor: deptColors }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
    <?php endif; ?>

    // Gender doughnut chart
    <?php if (!empty($byGender)): ?>
    var genderLabels = [<?php foreach ($byGender as $g) echo "'" . e($g['gender'] === 'M' ? 'Homme' : ($g['gender'] === 'F' ? 'Femme' : $g['gender'])) . "',"; ?>];
    var genderData = [<?php foreach ($byGender as $g) echo (int)$g['count'] . ","; ?>];
    new Chart(document.getElementById('chart-gender'), {
        type: 'doughnut',
        data: {
            labels: genderLabels,
            datasets: [{ data: genderData, backgroundColor: ['#007bff','#dc3545','#6c757d'] }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
    <?php endif; ?>
});
</script>