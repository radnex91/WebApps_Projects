<?php $title = 'Taxes'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-percent"></i> Taxes</h4>
    <a href="<?= url('accounting/taxes/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvelle taxe</a>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table table-hover mb-0">
        <thead><tr><th>Nom</th><th>Taux</th><th>Type</th><th>Actif</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($taxes as $t): ?>
            <tr><td><?= e($t['nom']) ?></td><td><?= $t['taux'] ?>%</td><td><span class="badge bg-info"><?= strtoupper($t['type']) ?></span></td><td><?= $t['actif'] ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-danger">Non</span>' ?></td><td>
                <a href="<?= url('accounting/taxes/edit/' . $t['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <a href="<?= url('accounting/taxes/delete/' . $t['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a>
            </td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
