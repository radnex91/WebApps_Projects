<?php $title = 'Facture ' . e($invoice['numero_facture']); ?>
<style>@media print { .navbar,.btn { display:none!important } }</style>
<div class="row"><div class="col-md-8 mx-auto">
    <div class="card"><div class="card-header d-flex justify-content-between"><h5 class="mb-0"><?= e($invoice['numero_facture']) ?></h5><button class="btn btn-sm btn-outline-light" onclick="window.print()"><i class="bi bi-printer"></i></button></div>
    <div class="card-body">
        <div class="row mb-4"><div class="col-md-6"><h6>AureliaHost</h6><p class="text-muted small mb-0">123 Av. Mohammed V<br>Casablanca, Maroc</p></div><div class="col-md-6 text-end"><h6>Facture</h6><p class="small"><?= e($invoice['numero_facture']) ?><br>Émise le <?= formatDate($invoice['date_emission']) ?><br><?= $invoice['date_echeance'] ? 'Échéance : '.formatDate($invoice['date_echeance']) : '' ?></p></div></div>
        <div class="mb-4"><strong>Client :</strong> <?= e(($invoice['client_prenom']??'').' '.($invoice['client_nom']??'')) ?><br><small><?= e($invoice['client_email'] ?? '') ?> / <?= e($invoice['client_tel'] ?? '') ?></small></div>
        <table class="table table-bordered"><thead class="table-dark"><tr><th>Description</th><th>Qté</th><th>Prix unit.</th><th>Total</th></tr></thead><tbody><?php foreach ($items as $it): ?><tr><td><?=e($it['description'])?></td><td><?=$it['quantite']?></td><td><?=formatMoney($it['prix_unitaire'])?></td><td><?=formatMoney($it['total'])?></td></tr><?php endforeach; ?></tbody><tfoot><tr><th colspan="3" class="text-end">Total HT</th><th><?=formatMoney($invoice['montant_ht'])?></th></tr><tr><th colspan="3" class="text-end">TVA</th><th><?=formatMoney($invoice['montant_tva'])?></th></tr><tr class="table-primary"><th colspan="3" class="text-end">Total TTC</th><th><?=formatMoney($invoice['montant_ttc'])?></th></tr></tfoot></table>
        <a href="<?= url('accounting/invoices') ?>" class="btn btn-secondary">Retour</a>
    </div></div>
</div></div>
