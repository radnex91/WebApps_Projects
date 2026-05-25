<?php
$pageTitle = 'Pointage';
$activeMenu = 'attendance';
$breadcrumbs = ['Pointage' => ''];
?>
<div class="row mb-3">
    <div class="col-md-6"><h3>Pointage - <?php echo getMonthName($month) . ' ' . $year; ?></h3></div>
    <div class="col-md-6 text-right">
        <?php if (Auth::hasPermission('attendance', 'create')): ?>
        <a href="<?php echo APP_URL; ?>/attendance/create" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> Enregistrer</a>
        <?php endif; ?>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <form method="GET" class="form-inline">
            <select name="month" class="form-control mr-2">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>><?php echo getMonthName($m); ?></option>
                <?php endfor; ?>
            </select>
            <select name="year" class="form-control mr-2">
                <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-info"><i class="fas fa-search"></i></button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-bordered table-striped dataTable">
            <thead>
                <tr>
                    <th>Employé</th>
                    <th>Date</th>
                    <th>Arrivée</th>
                    <th>Départ</th>
                    <th>Statut</th>
                    <th>Retard (min)</th>
                    <th>Heures supp (min)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attendance as $a): ?>
                <tr>
                    <td><?php echo e($a['first_name'] . ' ' . $a['last_name']); ?></td>
                    <td><?php echo formatDate($a['date']); ?></td>
                    <td><?php echo e($a['time_in'] ?? '-'); ?></td>
                    <td><?php echo e($a['time_out'] ?? '-'); ?></td>
                    <td><?php echo getStatusBadge($a['status']); ?></td>
                    <td><?php echo $a['late_minutes'] > 0 ? '<span class="text-danger">' . $a['late_minutes'] . '</span>' : '0'; ?></td>
                    <td><?php echo $a['overtime_minutes'] > 0 ? '<span class="text-success">' . $a['overtime_minutes'] . '</span>' : '0'; ?></td>
                    <td>
                        <?php if (Auth::hasPermission('attendance', 'edit')): ?>
                        <a href="<?php echo APP_URL; ?>/attendance/<?php echo $a['id']; ?>/edit" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>