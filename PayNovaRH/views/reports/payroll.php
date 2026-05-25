<?php
$title = 'Rapport Paie';
$activeMenu = 'reports';
$breadcrumbs = ['Rapports' => '/reports', 'Paie' => null];
?>

<!-- Year Filter -->
<div class="row mb-3">
    <div class="col-12">
        <form method="get" class="form-inline">
            <div class="form-group mr-2">
                <label for="year" class="mr-1">Année</label>
                <select name="year" id="year" class="form-control form-control-sm custom-select" onchange="this.form.submit()">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($year ?? date('Y')) == $y ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Monthly Summary -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-money-bill-wave mr-1"></i> Résumé mensuel - <?php echo e($year ?? date('Y')); ?>
                </h3>
            </div>
            <div class="card-body">
                <?php if (!empty($monthly)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th>Mois</th>
                                    <th class="text-right">Brut</th>
                                    <th class="text-right">Déductions</th>
                                    <th class="text-right">Net</th>
                                    <th class="text-center">Nb employés</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $totalGross = 0;
                                $totalDeductions = 0;
                                $totalNet = 0;
                                $totalEmployees = 0;
                                ?>
                                <?php foreach ($monthly as $m): ?>
                                    <?php
                                    $totalGross += (float)($m['total_gross'] ?? 0);
                                    $totalDeductions += (float)($m['total_deductions'] ?? 0);
                                    $totalNet += (float)($m['total_net'] ?? 0);
                                    $totalEmployees = max($totalEmployees, (int)($m['employee_count'] ?? 0));
                                    ?>
                                    <tr>
                                        <td><strong><?php echo getMonthName((int)($m['month'] ?? 1)); ?></strong></td>
                                        <td class="text-right"><?php echo formatMoney($m['total_gross'] ?? 0); ?></td>
                                        <td class="text-right text-danger">-<?php echo formatMoney($m['total_deductions'] ?? 0); ?></td>
                                        <td class="text-right font-weight-bold text-success"><?php echo formatMoney($m['total_net'] ?? 0); ?></td>
                                        <td class="text-center"><?php echo (int)($m['employee_count'] ?? 0); ?></td>
                                        <td><?php echo getStatusBadge($m['status'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-dark font-weight-bold">
                                    <td>TOTAL</td>
                                    <td class="text-right"><?php echo formatMoney($totalGross); ?></td>
                                    <td class="text-right text-danger">-<?php echo formatMoney($totalDeductions); ?></td>
                                    <td class="text-right text-success"><?php echo formatMoney($totalNet); ?></td>
                                    <td class="text-center"><?php echo $totalEmployees; ?></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted py-4">Aucune donnée de paie pour cette année.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Payroll Chart -->
<?php if (!empty($monthly)): ?>
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-bar mr-1"></i> Évolution mensuelle</h3>
            </div>
            <div class="card-body">
                <canvas id="chart-payroll" height="100"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
$(function () {
    <?php if (!empty($monthly)): ?>
    var labels = [<?php foreach ($monthly as $m) echo "'" . getMonthName((int)($m['month'] ?? 1)) . "',"; ?>];
    var grossData = [<?php foreach ($monthly as $m) echo number_format((float)($m['total_gross'] ?? 0), 2, '.', '') . ","; ?>];
    var netData = [<?php foreach ($monthly as $m) echo number_format((float)($m['total_net'] ?? 0), 2, '.', '') . ","; ?>];
    var deductionsData = [<?php foreach ($monthly as $m) echo number_format((float)($m['total_deductions'] ?? 0), 2, '.', '') . ","; ?>];

    new Chart(document.getElementById('chart-payroll'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Brut', data: grossData, backgroundColor: 'rgba(0,123,255,0.7)' },
                { label: 'Net', data: netData, backgroundColor: 'rgba(40,167,69,0.7)' },
                { label: 'Déductions', data: deductionsData, backgroundColor: 'rgba(220,53,69,0.7)' }
            ]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { position: 'top' } }
        }
    });
    <?php endif; ?>
});
</script>