<?php $title = 'Modifier chambre ' . e($room['numero']); ?>
<div class="row"><div class="col-md-6 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-pencil"></i> Modifier chambre <?= e($room['numero']) ?></h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('hotel/rooms/' . $room['id']) ?>">
            <div class="mb-3"><label class="form-label">Numéro *</label><input type="text" name="numero" class="form-control" value="<?= e($room['numero']) ?>" required></div>
            <div class="mb-3"><label class="form-label">Type *</label>
                <select name="room_type_id" class="form-select" required>
                    <?php foreach ($roomTypes as $rt): ?>
                    <option value="<?= $rt['id'] ?>" <?= $rt['id'] == $room['room_type_id'] ? 'selected' : '' ?>><?= e($rt['nom']) ?> (<?= formatMoney($rt['prix_base']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="form-label">Étage</label><input type="number" name="etage" class="form-control" value="<?= $room['etage'] ?>"></div>
            <div class="mb-3"><label class="form-label">Statut</label>
                <select name="statut" class="form-select">
                    <?php foreach (['disponible','occupee','maintenance','nettoyage'] as $s): ?>
                    <option value="<?= $s ?>" <?= $room['statut'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"><?= e($room['description']) ?></textarea></div>
            <button type="submit" class="btn btn-primary">Mettre à jour</button>
            <a href="<?= url('hotel/rooms') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
