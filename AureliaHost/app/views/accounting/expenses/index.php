<?php $title = 'Dépenses'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-cart"></i> Dépenses</h4>
    <a href="<?= url('accounting/expenses/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvelle dépense</a>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table table-hover mb-0">
        <thead><tr><th>Description</th><th>Catégorie</th><th>Montant</th><th>Date</th><th>Par</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($expenses as $e): ?>
            <tr><td><?= e($e['description']) ?></td><td><span class="badge bg-info"><?= $e['categorie'] ?></span></td><td class="text-danger fw-bold"><?= formatMoney($e['montant']) ?></td><td><?= formatDate($e['date_depense']) ?></td><td><?= e(($e['user_prenom']??'').' '.($e['user_nom']??'')) ?></td>
            <td><a href="<?= url('accounting/expenses/edit/' . $e['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a><a href="<?= url('accounting/expenses/delete/' . $e['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
