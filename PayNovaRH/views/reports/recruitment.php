<?php
$title = 'Rapport Recrutement';
$activeMenu = 'reports';
$breadcrumbs = ['Rapports' => '/reports', 'Recrutement' => null];
?>

<!-- By Status Chart -->
<div class="row">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-pie mr-1"></i> Candidatures par statut</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($byStatus)): ?>
                    <canvas id="chart-status" height="300"></canvas>
                <?php else: ?>
                    <p class="text-center text-muted py-4">Aucune donnée disponible.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- By Job Table -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-bullhorn mr-1"></i> Candidatures par offre d'emploi</h3>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($byJob)): ?>
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Offre d'emploi</th>
                                <th class="text-center">Candidatures</th>
                                <th class="text-center">Embauchés</th>
                                <th class="text-center">Taux d'embauche</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byJob as $j): ?>
                                <tr>
                                    <td><?php echo e($j['title'] ?? 'Sans titre'); ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-primary"><?php echo (int)($j['application_count'] ?? 0); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-success"><?php echo (int)($j['hired_count'] ?? 0); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $total = (int)($j['application_count'] ?? 0);
                                        $hired = (int)($j['hired_count'] ?? 0);
                                        $rate = $total > 0 ? round(($hired / $total) * 100, 1) : 0;
                                        ?>
                                        <span class="badge badge-<?php echo $rate >= 30 ? 'success' : ($rate >= 10 ? 'warning' : 'secondary'); ?>">
                                            <?php echo $rate; ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-muted py-4">Aucune offre d'emploi trouvée.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    <?php if (!empty($byStatus)): ?>
    var statusLabels = [<?php foreach ($byStatus as $s) echo "'" . e($s['status']) . "',"; ?>];
    var statusData = [<?php foreach ($byStatus as $s) echo (int)($s['count'] ?? 0) . ","; ?>];
    var statusColors = {
        'nouveau': '#17a2b8',
        'entretien': '#ffc107',
        'test': '#6f42c1',
        'offre': '#28a745',
        'embauché': '#28a745',
        'refusé': '#dc3545'
    };
    var colors = statusLabels.map(function(label) { return statusColors[label] || '#6c757d'; });

    new Chart(document.getElementById('chart-status'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{ data: statusData, backgroundColor: colors }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
    <?php endif; ?>
});
</script>