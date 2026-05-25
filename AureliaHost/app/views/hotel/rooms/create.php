<?php $title = 'Nouvelle chambre'; ?>
<div class="row"><div class="col-md-6 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-plus-circle"></i> Nouvelle chambre</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('hotel/rooms') ?>">
            <div class="mb-3"><label class="form-label">Numéro *</label><input type="text" name="numero" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Type de chambre *</label>
                <select name="room_type_id" class="form-select" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($roomTypes as $rt): ?>
                    <option value="<?= $rt['id'] ?>"><?= e($rt['nom']) ?> (<?= formatMoney($rt['prix_base']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="form-label">Étage</label><input type="number" name="etage" class="form-control" value="1"></div>
            <div class="mb-3"><label class="form-label">Statut</label>
                <select name="statut" class="form-select">
                    <option value="disponible">Disponible</option><option value="occupee">Occupée</option>
                    <option value="maintenance">Maintenance</option><option value="nettoyage">Nettoyage</option>
                </select>
            </div>
            <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button>
            <a href="<?= url('hotel/rooms') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
