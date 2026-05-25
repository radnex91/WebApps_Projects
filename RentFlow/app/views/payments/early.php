<?php
$title = 'Paiements Anticipés';
$content = '
<div class="container-fluid">
    <h1 class="h3 mb-4"><i class="bi bi-check-circle text-success"></i> Paiements Anticipés</h1>
    <div class="card">
        <div class="card-body">
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> ' . count($payments) . ' paiement(s) anticipé(s)
            </div>
            <table class="table table-hover datatable">
                <thead><tr><th>ID</th><th>Lot</th><th>Bailleur</th><th>Agence</th><th>Montant</th><th>ÿ‰chéance</th><th>Paiement</th></tr></thead>
                <tbody>';
                    foreach ($payments as $payment) {
                        $content .= '<tr class="table-success">
                            <td>' . $payment['id'] . '</td>
                            <td>' . htmlspecialchars($payment['batch_name']) . '</td>
                            <td>' . htmlspecialchars($payment['landlord_name']) . '</td>
                            <td>' . htmlspecialchars($payment['agency_name']) . '</td>
                            <td>' . formatCurrency($payment['amount']) . '</td>
                            <td>' . $payment['due_date'] . '</td>
                            <td>' . $payment['paid_date'] . '</td>
                        </tr>';
                    }
                $content .= '</tbody>
            </table>
        </div>
    </div>
</div>';
require __DIR__ . '/../layouts/main.php';
