<?php $title = 'Modifier dépense'; ?>
<div class="row"><div class="col-md-6 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-pencil"></i> Modifier dépense</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('accounting/expenses/' . $expense['id']) ?>">
            <div class="mb-3"><label class="form-label">Description *</label><input type="text" name="description" class="form-control" value="<?= e($expense['description']) ?>" required></div>
            <div class="row mb-3"><div class="col-md-6"><label class="form-label">Montant *</label><input type="number" step="0.01" name="montant" class="form-control" value="<?= $expense['montant'] ?>" required></div><div class="col-md-6"><label class="form-label">Catégorie</label><select name="categorie" class="form-select"><?php foreach(['salaires','maintenance','fournitures','services','loyer','autre'] as $c): ?><option value="<?=$c?>" <?=$expense['categorie']===$c?'selected':''?>><?=ucfirst($c)?></option><?php endforeach; ?></select></div></div>
            <div class="mb-3"><label class="form-label">Date *</label><input type="date" name="date_depense" class="form-control" value="<?= $expense['date_depense'] ?>" required></div>
            <button type="submit" class="btn btn-primary">Mettre à jour</button>
            <a href="<?= url('accounting/expenses') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
