<?php $title = 'Types de chambres'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-tags"></i> Types de chambres</h4>
    <a href="<?= url('hotel/room-types/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouveau</a>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table table-hover mb-0">
        <thead><tr><th>Nom</th><th>Prix base</th><th>Capacité</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($roomTypes as $rt): ?>
            <tr>
                <td><?= e($rt['nom']) ?></td>
                <td><?= formatMoney($rt['prix_base']) ?>/nuit</td>
                <td><?= $rt['capacite'] ?> pers.</td>
                <td>
                    <a href="<?= url('hotel/room-types/edit/' . $rt['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <a href="<?= url('hotel/room-types/delete/' . $rt['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
