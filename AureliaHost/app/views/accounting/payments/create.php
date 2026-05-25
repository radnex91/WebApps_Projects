<?php $title = 'Nouveau paiement'; ?>
<div class="row"><div class="col-md-6 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-cash"></i> Nouveau paiement</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('accounting/payments') ?>">
            <div class="mb-3"><label class="form-label">Facture *</label><select name="invoice_id" class="form-select" required><option value="">— Sélectionner —</option><?php foreach($invoices as $i): ?><option value="<?=$i['id']?>"><?=e($i['numero_facture'])?> — <?=formatMoney($i['montant_ttc'])?></option><?php endforeach; ?></select></div>
            <div class="mb-3"><label class="form-label">Montant *</label><input type="number" step="0.01" name="montant" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Mode de paiement</label><select name="mode_paiement" class="form-select"><?php foreach(['especes','carte','virement','cheque'] as $m): ?><option value="<?=$m?>"><?=ucfirst($m)?></option><?php endforeach; ?></select></div>
            <div class="mb-3"><label class="form-label">Référence</label><input type="text" name="reference" class="form-control" placeholder="N° chèque, transaction..."></div>
            <div class="mb-3"><label class="form-label">Date *</label><input type="date" name="date_paiement" class="form-control" value="<?=date('Y-m-d')?>" required></div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a href="<?= url('accounting/payments') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
