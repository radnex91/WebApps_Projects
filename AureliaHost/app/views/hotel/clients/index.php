<?php $title = 'Clients'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-people"></i> Clients</h4>
    <a href="<?= url('hotel/clients/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouveau client</a>
</div>
<form class="mb-3" method="get">
    <div class="input-group"><input type="text" name="search" class="form-control" placeholder="Rechercher..." value="<?= e($search) ?>"><button class="btn btn-outline-primary"><i class="bi bi-search"></i></button></div>
</form>
<div class="card"><div class="card-body p-0">
    <table class="table table-hover mb-0">
        <thead><tr><th>Nom</th><th>Prénom</th><th>Email</th><th>Téléphone</th><th>Ville</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($clients as $c): ?>
            <tr>
                <td><?= e($c['nom']) ?></td><td><?= e($c['prenom']) ?></td>
                <td><?= e($c['email']) ?></td><td><?= e($c['telephone']) ?></td>
                <td><?= e($c['ville']) ?></td>
                <td>
                    <a href="<?= url('hotel/clients/show/' . $c['id']) ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></a>
                    <a href="<?= url('hotel/clients/edit/' . $c['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <a href="<?= url('hotel/clients/delete/' . $c['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
