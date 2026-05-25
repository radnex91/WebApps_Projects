<?php
// Standalone payslip - no layout
$entry = $entry ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Fiche de paie - <?php echo e($entry['first_name'] ?? '') . ' ' . e($entry['last_name'] ?? ''); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .payslip { max-width: 800px; margin: 20px auto; border: 2px solid #333; padding: 20px; }
        .payslip-header { text-align: center; border-bottom: 3px double #333; padding-bottom: 15px; margin-bottom: 20px; }
        .payslip-header h1 { font-size: 24px; margin: 0; }
        .payslip-header h2 { font-size: 16px; color: #666; }
        .section-title { background-color: #f8f9fa; font-weight: bold; padding: 5px 10px; border: 1px solid #ddd; }
        .total-row { font-size: 1.1em; font-weight: bold; background-color: #f8f9fa; }
        .signature-area { margin-top: 60px; }
        .signature-area .col-6 { border-top: 1px solid #333; padding-top: 10px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print text-center mb-3">
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print mr-1"></i> Imprimer</button>
        <button onclick="window.close()" class="btn btn-secondary ml-2">Fermer</button>
    </div>

    <div class="payslip">
        <div class="payslip-header">
            <h1>PAYNOVARH</h1>
            <h2>FICHE DE PAIE</h2>
            <p class="mb-0">Période : <strong><?php echo getMonthName($entry['month'] ?? 1) . ' ' . ($entry['year'] ?? date('Y')); ?></strong></p>
        </div>

        <div class="row mb-3">
            <div class="col-6">
                <h6 class="section-title">Informations employé</h6>
                <p><strong>Nom :</strong> <?php echo e($entry['first_name'] ?? '') . ' ' . e($entry['last_name'] ?? ''); ?></p>
                <p><strong>Département :</strong> <?php echo e($entry['department_name'] ?? '-'); ?></p>
                <p><strong>Poste :</strong> <?php echo e($entry['position_name'] ?? '-'); ?></p>
                <?php if (!empty($entry['cin'])): ?>
                <p><strong>CIN :</strong> <?php echo e($entry['cin']); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-6">
                <h6 class="section-title">Détails période</h6>
                <p><strong>Mois :</strong> <?php echo getMonthName($entry['month'] ?? 1); ?></p>
                <p><strong>Année :</strong> <?php echo e($entry['year'] ?? date('Y')); ?></p>
                <p><strong>Date de paiement :</strong> <?php echo $entry['payment_date'] ?? '-'; ?></p>
            </div>
        </div>

        <table class="table table-bordered table-sm">
            <thead>
                <tr class="section-title">
                    <th>Rubrique</th>
                    <th class="text-right">Montant (MAD)</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="2" class="section-title">Gains</td></tr>
                <tr><td>Salaire de base</td><td class="text-right"><?php echo formatMoney($entry['base_salary'] ?? 0); ?></td></tr>
                <?php if (($entry['overtime_amount'] ?? 0) > 0): ?>
                <tr><td>Heures supplémentaires</td><td class="text-right"><?php echo formatMoney($entry['overtime_amount']); ?></td></tr>
                <?php endif; ?>
                <?php if (($entry['bonus'] ?? 0) > 0): ?>
                <tr><td>Primes</td><td class="text-right"><?php echo formatMoney($entry['bonus']); ?></td></tr>
                <?php endif; ?>
                <?php if (($entry['other_allowances'] ?? 0) > 0): ?>
                <tr><td>Autres indemnités</td><td class="text-right"><?php echo formatMoney($entry['other_allowances']); ?></td></tr>
                <?php endif; ?>
                <tr class="font-weight-bold"><td>SALAIRE BRUT</td><td class="text-right"><?php echo formatMoney($entry['gross_salary'] ?? 0); ?></td></tr>

                <tr><td colspan="2" class="section-title">Déductions</td></tr>
                <tr><td>CNSS (4.48%)</td><td class="text-right"><?php echo formatMoney($entry['cnss_employee'] ?? 0); ?></td></tr>
                <tr><td>AMO (2.26%)</td><td class="text-right"><?php echo formatMoney($entry['amo_employee'] ?? 0); ?></td></tr>
                <?php if (($entry['cimr'] ?? 0) > 0): ?>
                <tr><td>CIMR</td><td class="text-right"><?php echo formatMoney($entry['cimr']); ?></td></tr>
                <?php endif; ?>
                <tr><td>IR</td><td class="text-right"><?php echo formatMoney($entry['itr'] ?? 0); ?></td></tr>
                <?php if (($entry['advance'] ?? 0) > 0): ?>
                <tr><td>Avance</td><td class="text-right"><?php echo formatMoney($entry['advance']); ?></td></tr>
                <?php endif; ?>
                <?php if (($entry['other_deductions'] ?? 0) > 0): ?>
                <tr><td>Autres déductions</td><td class="text-right"><?php echo formatMoney($entry['other_deductions']); ?></td></tr>
                <?php endif; ?>
                <tr class="font-weight-bold"><td>TOTAL DÉDUCTIONS</td><td class="text-right text-danger"><?php echo formatMoney($entry['total_deductions'] ?? 0); ?></td></tr>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td>NET À PAYER</td>
                    <td class="text-right payslip-total"><?php echo formatMoney($entry['net_salary'] ?? 0); ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="row mt-4">
            <div class="col-6">
                <small>Part employeur : CNSS <?php echo formatMoney($entry['cnss_employer'] ?? 0); ?> | AMO <?php echo formatMoney($entry['amo_employer'] ?? 0); ?></small>
            </div>
        </div>

        <div class="signature-area row mt-5">
            <div class="col-6 text-center">
                <strong>Signature de l'employé</strong>
            </div>
            <div class="col-6 text-center">
                <strong>Signature de l'employeur</strong>
            </div>
        </div>
    </div>
</body>
</html>