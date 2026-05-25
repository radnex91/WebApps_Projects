<?php
$pageTitle = 'Soldes de congés';
$activeMenu = 'leaves';
$breadcrumbs = ['Congés' => '/leaves', 'Soldes' => ''];
?>
<div class="row mb-3">
    <div class="col-md-6">
        <h3>Soldes de congés - <?php echo $year; ?></h3>
    </div>
    <div class="col-md-6 text-right">
        <form method="GET" class="form-inline" style="display:inline">
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
                    <th>Type de congé</th>
                    <th>Total</th>
                    <th>Utilisé</th>
                    <th>Restant</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($balances as $b): ?>
                <tr>
                    <td><?php echo e($b['first_name'] . ' ' . $b['last_name']); ?></td>
                    <td><?php echo e($b['leave_type']); ?></td>
                    <td class="font-weight-bold"><?php echo $b['total_days']; ?></td>
                    <td class="text-danger"><?php echo $b['used_days']; ?></td>
                    <td class="text-success font-weight-bold"><?php echo $b['remaining_days']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>