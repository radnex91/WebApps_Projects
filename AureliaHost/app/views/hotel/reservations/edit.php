<?php $title = 'Modifier réservation'; ?>
<div class="row"><div class="col-md-6 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-pencil"></i> Modifier réservation #<?= $reservation['id'] ?></h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('hotel/reservations/' . $reservation['id']) ?>">
            <div class="row mb-3"><div class="col-md-6"><label class="form-label">Check-in</label><input type="date" name="date_checkin" class="form-control" value="<?= $reservation['date_checkin'] ?>" required></div><div class="col-md-6"><label class="form-label">Check-out</label><input type="date" name="date_checkout" class="form-control" value="<?= $reservation['date_checkout'] ?>" required></div></div>
            <div class="mb-3"><label class="form-label">Statut</label>
                <select name="statut" class="form-select">
                    <?php foreach (['confirmee','en_cours','terminee','annulee'] as $s): ?>
                    <option value="<?= $s ?>" <?= $reservation['statut']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= e($reservation['notes']) ?></textarea></div>
            <button type="submit" class="btn btn-primary">Mettre à jour</button>
            <a href="<?= url('hotel/reservations') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
