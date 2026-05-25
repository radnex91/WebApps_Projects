<?php
$title = 'Paiements en Retard';
$content = '
<div class="container-fluid">
    <h1 class="h3 mb-4"><i class="bi bi-exclamation-triangle text-danger"></i> Paiements en Retard</h1>
    <div class="card">
        <div class="card-body">
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> ' . count($payments) . ' paiement(s) en retard détecté(s)
            </div>
            <table class="table table-hover datatable">
                <thead><tr><th>ID</th><th>Lot</th><th>Bailleur</th><th>Agence</th><th>Montant</th><th>Échéance</th><th>Paiement</th><th>Actions</th></tr></thead>
                <tbody>';
                    foreach ($payments as $payment) {
                        $content .= '<tr class="table-danger">
                            <td>' . $payment['id'] . '</td>
                            <td>' . htmlspecialchars($payment['batch_name']) . '</td>
                            <td>' . htmlspecialchars($payment['landlord_name']) . '</td>
                            <td>' . htmlspecialchars($payment['agency_name']) . '</td>
                            <td>' . formatCurrency($payment['amount']) . '</td>
                            <td>' . $payment['due_date'] . '</td>
                            <td>' . ($payment['paid_date'] ?? '<span class="text-muted">Non payé</span>') . '</td>
                            <td>
                                <form method="POST" action="' . BASE_URL . '/payments/' . $payment['id'] . '/remind" style="display:inline;">
                                    ' . Csrf::field() . '
                                    <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm(\'Envoyer un rappel ?\')"><i class="bi bi-bell"></i> Rappeler</button>
                                </form>
                                <a href="' . BASE_URL . '/payments/' . $payment['id'] . '/edit" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>';
                    }
                $content .= '</tbody>
            </table>
        </div>
    </div>
</div>';
require __DIR__ . '/../layouts/main.php';
