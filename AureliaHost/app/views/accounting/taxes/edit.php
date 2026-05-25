<?php $title = 'Modifier taxe'; ?>
<div class="row"><div class="col-md-6 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-pencil"></i> Modifier taxe</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('accounting/taxes/' . $tax['id']) ?>">
            <div class="mb-3"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" value="<?= e($tax['nom']) ?>" required></div>
            <div class="row mb-3"><div class="col-md-6"><label class="form-label">Taux (%) *</label><input type="number" step="0.01" name="taux" class="form-control" value="<?= $tax['taux'] ?>" required></div><div class="col-md-6"><label class="form-label">Type</label><select name="type" class="form-select"><option value="tva" <?=$tax['type']==='tva'?'selected':''?>>TVA</option><option value="is" <?=$tax['type']==='is'?'selected':''?>>IS</option><option value="autre" <?=$tax['type']==='autre'?'selected':''?>>Autre</option></select></div></div>
            <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"><?= e($tax['description']) ?></textarea></div>
            <div class="mb-3"><label class="form-label">Statut</label><select name="actif" class="form-select"><option value="1" <?=$tax['actif']?'selected':''?>>Actif</option><option value="0" <?=!$tax['actif']?'selected':''?>>Inactif</option></select></div>
            <button type="submit" class="btn btn-primary">Mettre à jour</button>
            <a href="<?= url('accounting/taxes') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
