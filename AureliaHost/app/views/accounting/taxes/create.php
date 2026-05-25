<?php $title = 'Nouvelle taxe'; ?>
<div class="row"><div class="col-md-6 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-plus-circle"></i> Nouvelle taxe</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('accounting/taxes') ?>">
            <div class="mb-3"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" required></div>
            <div class="row mb-3"><div class="col-md-6"><label class="form-label">Taux (%) *</label><input type="number" step="0.01" name="taux" class="form-control" required></div><div class="col-md-6"><label class="form-label">Type</label><select name="type" class="form-select"><option value="tva">TVA</option><option value="is">IS</option><option value="autre">Autre</option></select></div></div>
            <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a href="<?= url('accounting/taxes') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
