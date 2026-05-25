<?php
$pageTitle = getMonthName($period['month']) . ' ' . $period['year'];
$activeMenu = 'payroll';
$breadcrumbs = ['Paie' => '/payroll', $pageTitle => ''];
?>
<div class="row mb-3">
    <div class="col-md-6">
        <h3><?php echo $pageTitle; ?></h3>
        <span class="ml-2"><?php echo getStatusBadge($period['status']); ?></span>
    </div>
    <div class="col-md-6 text-right">
        <?php if ($period['status'] === 'brouillon' || $period['status'] === 'en_cours'): ?>
        <a href="#" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Imprimer</a>
        <?php endif; ?>
    </div>
</div>

<!-- Summary cards -->
<div class="row mb-3">
    <div class="col-md-4">
        <div class="info-box bg-success">
            <span class="info-box-icon"><i class="fas fa-money-bill-wave"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Salaire Brut Total</span>
                <span class="info-box-number"><?php echo formatMoney($totals['gross']); ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="info-box bg-danger">
            <span class="info-box-icon"><i class="fas fa-minus-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Déductions Totales</span>
                <span class="info-box-number"><?php echo formatMoney($totals['deductions']); ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="info-box bg-navy">
            <span class="info-box-icon"><i class="fas fa-wallet"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Salaire Net Total</span>
                <span class="info-box-number"><?php echo formatMoney($totals['net']); ?></span>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-bordered table-striped">
            <thead class="thead-dark">
                <tr>
                    <th>Employé</th>
                    <th>Département</th>
                    <th class="text-right">Salaire de base</th>
                    <th class="text-right">Heures supp</th>
                    <th class="text-right">Primes</th>
                    <th class="text-right">Brut</th>
                    <th class="text-right">CNSS</th>
                    <th class="text-right">AMO</th>
                    <th class="text-right">CIMR</th>
                    <th class="text-right">IR</th>
                    <th class="text-right">Déductions</th>
                    <th class="text-right font-weight-bold">Net</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $e): ?>
                <tr>
                    <td><?php echo e($e['first_name'] . ' ' . $e['last_name']); ?></td>
                    <td><?php echo e($e['department_name'] ?? '-'); ?></td>
                    <td class="text-right"><?php echo formatMoney($e['base_salary']); ?></td>
                    <td class="text-right"><?php echo formatMoney($e['overtime_amount']); ?></td>
                    <td class="text-right"><?php echo formatMoney($e['bonus'] + $e['other_allowances']); ?></td>
                    <td class="text-right font-weight-bold"><?php echo formatMoney($e['gross_salary']); ?></td>
                    <td class="text-right"><?php echo formatMoney($e['cnss_employee']); ?></td>
                    <td class="text-right"><?php echo formatMoney($e['amo_employee']); ?></td>
                    <td class="text-right"><?php echo formatMoney($e['cimr']); ?></td>
                    <td class="text-right"><?php echo formatMoney($e['itr']); ?></td>
                    <td class="text-right text-danger"><?php echo formatMoney($e['total_deductions']); ?></td>
                    <td class="text-right font-weight-bold text-success"><?php echo formatMoney($e['net_salary']); ?></td>
                    <td><?php echo getStatusBadge($e['status']); ?></td>
                    <td>
                        <?php if (Auth::hasPermission('payroll', 'view')): ?>
                        <a href="<?php echo APP_URL; ?>/payroll/<?php echo $period['id']; ?>/payslip/<?php echo $e['id']; ?>" class="btn btn-sm btn-outline-info" title="Fiche de paie" target="_blank">
                            <i class="fas fa-file-invoice"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (Auth::hasPermission('payroll', 'edit') && $e['status'] === 'brouillon'): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-calculate" data-id="<?php echo $e['id']; ?>" title="Calculer">
                            <i class="fas fa-calculator"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="font-weight-bold">
                <tr>
                    <td colspan="5" class="text-right">Totaux</td>
                    <td class="text-right"><?php echo formatMoney($totals['gross']); ?></td>
                    <td colspan="4"></td>
                    <td class="text-right"><?php echo formatMoney($totals['deductions']); ?></td>
                    <td class="text-right text-success"><?php echo formatMoney($totals['net']); ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Calculate modal -->
<div class="modal fade" id="calculateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?php echo APP_URL; ?>/payroll/<?php echo $period['id']; ?>/calculate" id="calculateForm">
                <div class="modal-header bg-navy"><h5 class="modal-title">Calculer la paie</h5></div>
                <div class="modal-body" id="calculateModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Calculer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('.btn-calculate').on('click', function() {
    var id = $(this).data('id');
    // Load entry data and show modal
    $('#calculateModal').modal('show');
});
</script>