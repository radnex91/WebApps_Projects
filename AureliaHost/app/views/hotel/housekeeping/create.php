<?php $title = 'Nouvelle tâche d\'entretien'; ?>
<div class="row"><div class="col-md-6 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-plus-circle"></i> Nouvelle tâche</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('hotel/housekeeping') ?>">
            <div class="mb-3"><label class="form-label">Chambre *</label>
                <select name="room_id" class="form-select" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($rooms as $r): ?>
                    <option value="<?= $r['id'] ?>">Ch. <?= e($r['numero']) ?> — <?= e($r['type_nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="form-label">Date *</label><input type="date" name="date_nettoyage" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="mb-3"><label class="form-label">Assigné à</label>
                <select name="user_id" class="form-select"><option value="">— Aucun —</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['prenom'].' '.$u['nom']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="mb-3"><label class="form-label">Statut</label>
                <select name="statut" class="form-select"><option value="planifie">Planifiée</option><option value="en_cours">En cours</option><option value="termine">Terminée</option></select>
            </div>
            <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a href="<?= url('hotel/housekeeping') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
