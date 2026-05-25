<?php $title = 'Facture ' . e($invoice['numero_facture']); ?>
<div class="row">
    <div class="col-md-8">
        <div class="card mb-3"><div class="card-header d-flex justify-content-between"><h5 class="mb-0"><?= e($invoice['numero_facture']) ?></h5><span class="badge fs-6 bg-<?= $invoice['statut']==='payee'?'success':($invoice['statut']==='envoyee'?'warning':($invoice['statut']==='annulee'?'danger':'secondary')) ?>"><?=$invoice['statut']?></span></div>
        <div class="card-body">
            <div class="row mb-3"><div class="col-md-6"><h6>Client</h6><p><?= e(($invoice['client_prenom']??'').' '.($invoice['client_nom']??'')) ?><br><small><?= e($invoice['client_email'] ?? '') ?> / <?= e($invoice['client_tel'] ?? '') ?></small></p></div><div class="col-md-6 text-end"><h6>Dates</h6><p>Émise le <?= formatDate($invoice['date_emission']) ?><?= $invoice['date_echeance'] ? '<br>Échéance : '.formatDate($invoice['date_echeance']) : '' ?></p></div></div>
            <table class="table table-sm"><thead><tr><th>Description</th><th>Qté</th><th>Prix unit.</th><th>Total</th></tr></thead><tbody><?php foreach ($items as $it): ?><tr><td><?= e($it['description']) ?></td><td><?=$it['quantite']?></td><td><?=formatMoney($it['prix_unitaire'])?></td><td><?=formatMoney($it['total'])?></td></tr><?php endforeach; ?></tbody></table>
            <div class="text-end"><p>Total HT : <?= formatMoney($invoice['montant_ht']) ?><br>TVA : <?= formatMoney($invoice['montant_tva']) ?><br><strong class="fs-5">Total TTC : <?= formatMoney($invoice['montant_ttc']) ?></strong></p></div>
            <?php if ($invoice['notes']): ?><p class="text-muted small"><?= e($invoice['notes']) ?></p><?php endif; ?>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card mb-3"><div class="card-header"><h6 class="mb-0">Paiements (<?= formatMoney($totalPaye) ?> payé)</h6></div>
        <div class="card-body p-0"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Montant</th><th>Mode</th></tr></thead><tbody><?php foreach ($payments as $p): ?><tr><td><?=formatDate($p['date_paiement'])?></td><td><?=formatMoney($p['montant'])?></td><td><?=$p['mode_paiement']?></td></tr><?php endforeach; ?></tbody></table></div></div>
        <div class="card"><div class="card-header"><h6 class="mb-0">Modifier statut</h6></div>
        <div class="card-body">
            <form method="post" action="<?= url('accounting/invoices/' . $invoice['id']) ?>">
                <div class="mb-3"><select name="statut" class="form-select"><?php foreach(['brouillon','envoyee','payee','annulee'] as $s): ?><option value="<?=$s?>" <?=$invoice['statut']===$s?'selected':''?>><?=ucfirst($s)?></option><?php endforeach; ?></select></div>
                <button type="submit" class="btn btn-primary btn-sm">Mettre à jour</button>
            </form>
        </div></div>
        <a href="<?= url('accounting/invoices') ?>" class="btn btn-secondary mt-2">Retour</a>
    </div>
</div>
