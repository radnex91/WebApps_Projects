<?php $title = 'Nouvelle réservation'; ?>
<div class="row"><div class="col-lg-8 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-calendar-plus"></i> Nouvelle réservation</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('hotel/reservations') ?>">
            <div class="row mb-3">
                <div class="col-md-6"><label class="form-label">Client *</label>
                    <select name="client_id" class="form-select" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['prenom'].' '.$c['nom']) ?> — <?= e($c['telephone']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Créé par</label>
                    <input type="text" class="form-control" value="<?= e(Session::user()['prenom'].' '.Session::user()['nom']) ?>" disabled>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6"><label class="form-label">Check-in *</label><input type="date" name="date_checkin" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Check-out *</label><input type="date" name="date_checkout" class="form-control" required></div>
            </div>
            <div class="mb-3"><label class="form-label">Chambres *</label>
                <div class="row g-2">
                    <?php foreach ($rooms as $r): ?>
                    <div class="col-md-4">
                        <div class="form-check card p-2">
                            <input class="form-check-input" type="checkbox" name="room_ids[]" value="<?= $r['id'] ?>" id="room_<?= $r['id'] ?>">
                            <label class="form-check-label" for="room_<?= $r['id'] ?>">
                                <strong>Ch. <?= e($r['numero']) ?></strong>
                                <small class="d-block text-muted"><?= e($r['type_nom']) ?> — <?= formatMoney($r['prix_base']) ?>/nuit</small>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3"><label class="form-label">Services supplémentaires</label>
                <div class="row g-2">
                    <?php foreach ($services as $s): ?>
                    <div class="col-md-4">
                        <div class="form-check card p-2">
                            <input class="form-check-input" type="checkbox" name="service_ids[]" value="<?= $s['id'] ?>" id="svc_<?= $s['id'] ?>">
                            <label class="form-check-label" for="svc_<?= $s['id'] ?>">
                                <strong><?= e($s['nom']) ?></strong>
                                <small class="d-block text-muted"><?= formatMoney($s['prix']) ?></small>
                            </label>
                            <input type="number" name="service_quantites[]" class="form-control form-control-sm mt-1" value="1" min="1" style="width:80px">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Confirmer la réservation</button>
            <a href="<?= url('hotel/reservations') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
