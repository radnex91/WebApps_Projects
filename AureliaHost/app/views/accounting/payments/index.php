<?php $title = 'Paiements'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-credit-card"></i> Paiements</h4>
    <a href="<?= url('accounting/payments/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouveau paiement</a>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table table-hover mb-0">
        <thead><tr><th>Facture</th><th>Client</th><th>Montant</th><th>Mode</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($payments as $p): ?>
            <tr><td><?= e($p['numero_facture']) ?></td><td><?= e(($p['client_prenom']??'').' '.($p['client_nom']??'')) ?></td><td class="fw-bold text-success"><?= formatMoney($p['montant']) ?></td><td><span class="badge bg-info"><?= $p['mode_paiement'] ?></span></td><td><?= formatDate($p['date_paiement']) ?></td>
            <td><a href="<?= url('accounting/payments/delete/' . $p['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
