<?php $title = 'Services'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-cup-hot"></i> Services</h4>
    <a href="<?= url('hotel/services/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouveau service</a>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table table-hover mb-0">
        <thead><tr><th>Nom</th><th>Catégorie</th><th>Prix</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($services as $s): ?>
            <tr>
                <td><?= e($s['nom']) ?></td>
                <td><span class="badge bg-info"><?= $s['categorie'] ?></span></td>
                <td><?= $s['prix'] > 0 ? formatMoney($s['prix']) : '<span class="badge bg-success">Gratuit</span>' ?></td>
                <td>
                    <a href="<?= url('hotel/services/edit/' . $s['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <a href="<?= url('hotel/services/delete/' . $s['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
