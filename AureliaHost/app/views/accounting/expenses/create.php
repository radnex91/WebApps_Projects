<?php $title = 'Nouvelle dépense'; ?>
<div class="row"><div class="col-md-6 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-cart-plus"></i> Nouvelle dépense</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('accounting/expenses') ?>">
            <div class="mb-3"><label class="form-label">Description *</label><input type="text" name="description" class="form-control" required></div>
            <div class="row mb-3"><div class="col-md-6"><label class="form-label">Montant *</label><input type="number" step="0.01" name="montant" class="form-control" required></div><div class="col-md-6"><label class="form-label">Catégorie</label><select name="categorie" class="form-select"><?php foreach(['salaires','maintenance','fournitures','services','loyer','autre'] as $c): ?><option value="<?=$c?>"><?=ucfirst($c)?></option><?php endforeach; ?></select></div></div>
            <div class="mb-3"><label class="form-label">Date *</label><input type="date" name="date_depense" class="form-control" value="<?=date('Y-m-d')?>" required></div>
            <div class="mb-3"><label class="form-label">Justificatif (N° facture, chemin)</label><input type="text" name="justificatif" class="form-control"></div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a href="<?= url('accounting/expenses') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
