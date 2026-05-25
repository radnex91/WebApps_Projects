<?php
$title = 'Rapport Congés';
$activeMenu = 'reports';
$breadcrumbs = ['Rapports' => '/reports', 'Congés' => null];
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

<!-- By Type -->
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list mr-1"></i> Congés par type (approuvés)</h3>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($byType)): ?>
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Type de congé</th>
                                <th>Nombre</th>
                                <th>Jours totaux</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byType as $t): ?>
                                <tr>
                                    <td><?php echo e($t['name']); ?></td>
                                    <td><span class="badge badge-primary"><?php echo (int)$t['count']; ?></span></td>
                                    <td><?php echo number_format((float)($t['total_days'] ?? 0), 1); ?> jours</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-muted py-3">Aucun congé approuvé pour cette année.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- By Status -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-pie mr-1"></i> Congés par statut</h3>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($byStatus)): ?>
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Statut</th>
                                <th>Nombre</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byStatus as $s): ?>
                                <tr>
                                    <td><?php echo getStatusBadge($s['status']); ?></td>
                                    <td><span class="badge badge-secondary"><?php echo (int)$s['count']; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-muted py-3">Aucune demande pour cette année.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Full List -->
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-calendar-alt mr-1"></i> Liste complète des congés</h3>
            </div>
            <div class="card-body">
                <table id="leaves-table" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Type</th>
                            <th>Début</th>
                            <th>Fin</th>
                            <th>Jours</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($leaves)): ?>
                            <?php foreach ($leaves as $l): ?>
                                <tr>
                                    <td><?php echo e(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')); ?></td>
                                    <td><?php echo e($l['leave_type'] ?? ''); ?></td>
                                    <td><?php echo formatDate($l['start_date']); ?></td>
                                    <td><?php echo formatDate($l['end_date']); ?></td>
                                    <td><?php echo number_format((float)($l['total_days'] ?? 0), 1); ?></td>
                                    <td><?php echo getStatusBadge($l['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Aucun congé pour cette période.</td>
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
    $('#leaves-table').DataTable();
});
</script>