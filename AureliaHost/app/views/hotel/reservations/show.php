<?php $title = 'Réservation #' . $reservation['id']; ?>
<div class="row">
    <div class="col-md-8">
        <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Réservation #<?= $reservation['id'] ?></h5>
            <span class="badge fs-6 bg-<?= $reservation['statut']==='confirmee'?'primary':($reservation['statut']==='en_cours'?'success':($reservation['statut']==='terminee'?'secondary':'danger')) ?>"><?= $reservation['statut'] ?></span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Client</h6>
                    <p><?= e($reservation['client_prenom'].' '.$reservation['client_nom']) ?><br>
                    <small class="text-muted"><?= e($reservation['client_tel']) ?> / <?= e($reservation['client_email']) ?></small></p>
                </div>
                <div class="col-md-6">
                    <h6>Séjour</h6>
                    <p>Check-in : <?= formatDate($reservation['date_checkin']) ?><br>
                    Check-out : <?= formatDate($reservation['date_checkout']) ?><br>
                    <small class="text-muted"><?= (strtotime($reservation['date_checkout']) - strtotime($reservation['date_checkin'])) / 86400 ?> nuit(s)</small></p>
                </div>
            </div>
            <h6>Chambres</h6>
            <table class="table table-sm">
                <thead><tr><th>Chambre</th><th>Type</th><th>Prix/nuit</th></tr></thead>
                <tbody>
                    <?php foreach ($rooms as $r): ?>
                    <tr><td><?= e($r['numero']) ?></td><td><?= e($r['type_nom']) ?></td><td><?= formatMoney($r['prix_par_nuit']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <h6>Services</h6>
            <table class="table table-sm">
                <thead><tr><th>Service</th><th>Qté</th><th>Prix unit.</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach ($services as $s): ?>
                    <tr><td><?= e($s['nom']) ?></td><td><?= $s['quantite'] ?></td><td><?= formatMoney($s['prix_unitaire']) ?></td><td><?= formatMoney($s['prix_unitaire'] * $s['quantite']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="text-end fw-bold fs-5">Total : <?= formatMoney($reservation['montant_total']) ?></div>
            <?php if ($reservation['notes']): ?><p class="text-muted mt-2"><small>Notes : <?= e($reservation['notes']) ?></small></p><?php endif; ?>
            <a href="<?= url('hotel/reservations') ?>" class="btn btn-secondary">Retour</a>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-header"><h6 class="mb-0"><i class="bi bi-pencil"></i> Modifier le statut</h6></div>
        <div class="card-body">
            <form method="post" action="<?= url('hotel/reservations/' . $reservation['id']) ?>">
                <input type="hidden" name="date_checkin" value="<?= $reservation['date_checkin'] ?>">
                <input type="hidden" name="date_checkout" value="<?= $reservation['date_checkout'] ?>">
                <div class="mb-3"><label class="form-label">Statut</label>
                    <select name="statut" class="form-select">
                        <?php foreach (['confirmee','en_cours','terminee','annulee'] as $s): ?>
                        <option value="<?= $s ?>" <?= $reservation['statut']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= e($reservation['notes']) ?></textarea></div>
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
            </form>
        </div></div>
    </div>
</div>
