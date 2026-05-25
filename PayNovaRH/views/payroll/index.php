<?php
$pageTitle = 'Gestion de la paie';
$activeMenu = 'payroll';
$breadcrumbs = ['Paie' => ''];
?>
<div class="row mb-3">
    <div class="col-md-6"><h3>Gestion de la paie</h3></div>
    <div class="col-md-6 text-right">
        <?php if (Auth::hasPermission('payroll', 'create')): ?>
        <a href="<?php echo APP_URL; ?>/payroll/create" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> Nouvelle période</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-bordered table-striped dataTable">
            <thead>
                <tr>
                    <th>Mois</th>
                    <th>Année</th>
                    <th>Date de paiement</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($periods as $p): ?>
                <tr>
                    <td class="font-weight-bold"><?php echo getMonthName($p['month']); ?></td>
                    <td><?php echo $p['year']; ?></td>
                    <td><?php echo $p['payment_date'] ? formatDate($p['payment_date']) : '-'; ?></td>
                    <td><?php echo getStatusBadge($p['status']); ?></td>
                    <td>
                        <a href="<?php echo APP_URL; ?>/payroll/<?php echo $p['id']; ?>" class="btn btn-sm btn-info" title="Voir">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if (Auth::hasPermission('payroll', 'edit') && $p['status'] !== 'clôturé'): ?>
                        <form method="POST" action="<?php echo APP_URL; ?>/payroll/<?php echo $p['id']; ?>/close" style="display:inline">
                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Clôturer cette période ?')"><i class="fas fa-lock"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>