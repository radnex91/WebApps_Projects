<?php $title = 'Chauffeurs'; ?>
<div class="page-header">
    <h2>Chauffeurs</h2>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/chauffeurs/create" class="btn btn-primary">+ Ajouter</a>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Nom</th><th>Téléphone</th><th>Permis</th><th>Catégorie</th><th>Statut</th></tr></thead>
                <tbody>
                    <?php foreach ($chauffeurs['data'] as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c->getNomComplet()) ?></td>
                        <td><?= htmlspecialchars($c->telephone) ?></td>
                        <td><?= htmlspecialchars($c->permis) ?></td>
                        <td><?= htmlspecialchars($c->categorie_permis ?? '-') ?></td>
                        <td><?= $c->actif ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-red">Inactif</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>