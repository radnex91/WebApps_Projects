<?php $title = 'Modifier département'; ?>
<div class="row"><div class="col-md-6 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-pencil"></i> Modifier département</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('hr/departments/' . $dept['id']) ?>">
            <div class="mb-3"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" value="<?= e($dept['nom']) ?>" required></div>
            <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"><?= e($dept['description']) ?></textarea></div>
            <button type="submit" class="btn btn-primary">Mettre à jour</button>
            <a href="<?= url('hr/departments') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
