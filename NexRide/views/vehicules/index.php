<?php $title = 'Véhicules'; ?>
<div class="page-header">
    <h2>Véhicules</h2>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/vehicules/create" class="btn btn-primary">+ Ajouter</a>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Immatriculation</th><th>Marque</th><th>Modèle</th><th>Capacité</th><th>Type</th><th>Statut</th></tr></thead>
                <tbody>
                    <?php foreach ($vehicules['data'] as $v): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($v->immatriculation) ?></strong></td>
                        <td><?= htmlspecialchars($v->marque) ?></td>
                        <td><?= htmlspecialchars($v->modele) ?></td>
                        <td><?= $v->capacite ?> places</td>
                        <td><?= $v->type_vehicule ?></td>
                        <td><?= $v->actif ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-red">Inactif</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>