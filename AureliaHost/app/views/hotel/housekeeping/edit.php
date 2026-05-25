<?php $title = 'Modifier tâche entretien'; ?>
<div class="row"><div class="col-md-6 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-pencil"></i> Modifier tâche</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('hotel/housekeeping/' . $task['id']) ?>">
            <div class="mb-3"><label class="form-label">Chambre *</label>
                <select name="room_id" class="form-select" required>
                    <?php foreach ($rooms as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= $task['room_id'] == $r['id'] ? 'selected' : '' ?>>Ch. <?= e($r['numero']) ?> — <?= e($r['type_nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="form-label">Date *</label><input type="date" name="date_nettoyage" class="form-control" value="<?= $task['date_nettoyage'] ?>" required></div>
            <div class="mb-3"><label class="form-label">Assigné à</label>
                <select name="user_id" class="form-select"><option value="">— Aucun —</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $task['user_id'] == $u['id'] ? 'selected' : '' ?>><?= e($u['prenom'].' '.$u['nom']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="mb-3"><label class="form-label">Statut</label>
                <select name="statut" class="form-select">
                    <?php foreach (['planifie','en_cours','termine'] as $s): ?>
                    <option value="<?= $s ?>" <?= $task['statut'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= e($task['notes']) ?></textarea></div>
            <button type="submit" class="btn btn-primary">Mettre à jour</button>
            <a href="<?= url('hotel/housekeeping') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
