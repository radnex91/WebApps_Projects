<?php $title = 'Modifier facture'; ?>
<div class="row"><div class="col-md-6 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-pencil"></i> Modifier facture <?= e($invoice['numero_facture']) ?></h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('accounting/invoices/' . $invoice['id']) ?>">
            <div class="mb-3"><label class="form-label">Statut</label><select name="statut" class="form-select"><?php foreach(['brouillon','envoyee','payee','annulee'] as $s): ?><option value="<?=$s?>" <?=$invoice['statut']===$s?'selected':''?>><?=ucfirst($s)?></option><?php endforeach; ?></select></div>
            <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"><?= e($invoice['notes']) ?></textarea></div>
            <button type="submit" class="btn btn-primary">Mettre à jour</button>
            <a href="<?= url('accounting/invoices') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
