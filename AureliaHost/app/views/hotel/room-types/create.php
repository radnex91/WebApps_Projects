<?php $title = 'Nouveau type de chambre'; ?>
<div class="row"><div class="col-md-6 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-tags"></i> Nouveau type de chambre</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('hotel/room-types') ?>">
            <div class="mb-3"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Prix de base (€) *</label><input type="number" step="0.01" name="prix_base" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Capacité (personnes)</label><input type="number" name="capacite" class="form-control" value="1"></div>
            <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button>
            <a href="<?= url('hotel/room-types') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
