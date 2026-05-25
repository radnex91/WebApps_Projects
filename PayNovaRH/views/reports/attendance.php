<?php
$title = 'Rapport Pointage';
$activeMenu = 'reports';
$breadcrumbs = ['Rapports' => '/reports', 'Pointage' => null];
?>

<!-- Month/Year Filter -->
<div class="row mb-3">
    <div class="col-12">
        <form method="get" class="form-inline">
            <div class="form-group mr-2">
                <label for="month" class="mr-1">Mois</label>
                <select name="month" id="month" class="form-control form-control-sm custom-select">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo ($month ?? date('n')) == $m ? 'selected' : ''; ?>>
                            <?php echo getMonthName($m); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group mr-2">
                <label for="year" class="mr-1">Année</label>
                <select name="year" id="year" class="form-control form-control-sm custom-select">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($year ?? date('Y')) == $y ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="fas fa-search mr-1"></i> Filtrer
            </button>
            <a href="<?php echo APP_URL; ?>/reports/export/attendance?month=<?php echo $month ?? date('n'); ?>&year=<?php echo $year ?? date('Y'); ?>"
               class="btn btn-sm btn-outline-primary ml-2">
                <i class="fas fa-file-csv mr-1"></i> Exporter CSV
            </a>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row">
    <?php if (!empty($summary)): ?>
        <?php
        $totalPresent = (int)($summary['present_days'] ?? 0);
        $totalAbsent = (int)($summary['absent_days'] ?? 0);
        $totalLate = (int)($summary['late_days'] ?? 0);
        $totalLateMin = (int)($summary['total_late_minutes'] ?? 0);
        $totalOvertime = (int)($summary['total_overtime_minutes'] ?? 0);
        ?>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3><?php echo $totalPresent; ?></h3>
                    <p>Jours présents</p>
                </div>
                <div class="icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3><?php echo $totalAbsent; ?></h3>
                    <p>Jours absents</p>
                </div>
                <div class="icon"><i class="fas fa-times-circle"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3><?php echo $totalLate; ?></h3>
                    <p>Jours en retard</p>
                </div>
                <div class="icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3><?php echo number_format($totalOvertime / 60, 1); ?>h</h3>
                    <p>Heures supplémentaires</p>
                </div>
                <div class="icon"><i class="fas fa-business-time"></i></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- By Employee Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock mr-1"></i> Pointage par employé -
                    <?php echo getMonthName($month ?? date('n')); ?> <?php echo e($year ?? date('Y')); ?>
                </h3>
            </div>
            <div class="card-body">
                <table id="attendance-table" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Département</th>
                            <th class="text-center">Jours totaux</th>
                            <th class="text-center">Présent</th>
                            <th class="text-center">Absent</th>
                            <th class="text-center">Retard</th>
                            <th class="text-center">Retard (min)</th>
                            <th class="text-center">H. supp. (min)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($byEmployee)): ?>
                            <?php foreach ($byEmployee as $emp): ?>
                                <tr>
                                    <td><?php echo e(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')); ?></td>
                                    <td><?php echo e($emp['department_name'] ?? '-'); ?></td>
                                    <td class="text-center"><?php echo (int)($emp['total_days'] ?? 0); ?></td>
                                    <td class="text-center"><span class="text-success font-weight-bold"><?php echo (int)($emp['present_days'] ?? 0); ?></span></td>
                                    <td class="text-center"><span class="text-danger font-weight-bold"><?php echo (int)($emp['absent_days'] ?? 0); ?></span></td>
                                    <td class="text-center"><span class="text-warning font-weight-bold"><?php echo (int)($emp['late_days'] ?? 0); ?></span></td>
                                    <td class="text-center"><?php echo (int)($emp['total_late_minutes'] ?? 0); ?></td>
                                    <td class="text-center"><?php echo (int)($emp['total_overtime_minutes'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Aucune donnée de pointage pour cette période.</td>
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
    $('#attendance-table').DataTable();
});
</script>