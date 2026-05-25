<?php $title = 'Modifier service'; ?>
<div class="row"><div class="col-md-6 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-pencil"></i> Modifier service</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('hotel/services/' . $service['id']) ?>">
            <div class="mb-3"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" value="<?= e($service['nom']) ?>" required></div>
            <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"><?= e($service['description']) ?></textarea></div>
            <div class="row mb-3"><div class="col-md-6"><label class="form-label">Prix (€) *</label><input type="number" step="0.01" name="prix" class="form-control" value="<?= $service['prix'] ?>" required></div><div class="col-md-6"><label class="form-label">Catégorie</label><select name="categorie" class="form-select"><?php foreach(['restaurant','spa','blanchisserie','transport','autre'] as $cat): ?><option value="<?=$cat?>" <?=$service['categorie']===$cat?'selected':''?>><?=ucfirst($cat)?></option><?php endforeach; ?></select></div></div>
            <button type="submit" class="btn btn-primary">Mettre à jour</button>
            <a href="<?= url('hotel/services') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
