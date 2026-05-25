<?php $title = 'Factures'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-receipt"></i> Factures</h4>
    <a href="<?= url('accounting/invoices/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvelle facture</a>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table table-hover mb-0">
        <thead><tr><th>N°</th><th>Client</th><th>Date</th><th>HT</th><th>TVA</th><th>TTC</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($invoices as $i): ?>
            <tr><td><span class="fw-bold"><?= e($i['numero_facture']) ?></span></td><td><?= e(($i['client_prenom']??'').' '.($i['client_nom']??'')) ?></td><td><?= formatDate($i['date_emission']) ?></td><td><?= formatMoney($i['montant_ht']) ?></td><td><?= formatMoney($i['montant_tva']) ?></td><td class="fw-bold"><?= formatMoney($i['montant_ttc']) ?></td><td><span class="badge bg-<?= $i['statut']==='payee'?'success':($i['statut']==='envoyee'?'warning':($i['statut']==='annulee'?'danger':'secondary')) ?>"><?= $i['statut'] ?></span></td>
            <td>
                <a href="<?= url('accounting/invoices/show/' . $i['id']) ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></a>
                <a href="<?= url('accounting/invoices/print/' . $i['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></a>
                <a href="<?= url('accounting/invoices/edit/' . $i['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <a href="<?= url('accounting/invoices/delete/' . $i['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a>
            </td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
