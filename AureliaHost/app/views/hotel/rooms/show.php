<?php $title = 'Chambre ' . e($room['numero']); ?>
<div class="row"><div class="col-md-6 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-door-open"></i> Chambre <?= e($room['numero']) ?></h5></div>
    <div class="card-body">
        <table class="table">
            <tr><th>Numéro</th><td><?= e($room['numero']) ?></td></tr>
            <tr><th>Type</th><td><?= e($room['type_nom'] ?? 'N/A') ?></td></tr>
            <tr><th>Prix/nuit</th><td><?= formatMoney($room['prix_base'] ?? 0) ?></td></tr>
            <tr><th>Capacité</th><td><?= $room['capacite'] ?? 1 ?> pers.</td></tr>
            <tr><th>Étage</th><td><?= $room['etage'] ?></td></tr>
            <tr><th>Statut</th><td><span class="badge bg-<?= $room['statut']==='disponible'?'success':($room['statut']==='occupee'?'danger':'warning') ?>"><?= $room['statut'] ?></span></td></tr>
            <tr><th>Description</th><td><?= e($room['description']) ?></td></tr>
        </table>
        <a href="<?= url('hotel/rooms') ?>" class="btn btn-secondary">Retour</a>
    </div></div>
</div></div>
