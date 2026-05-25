<?php $title = 'Nouvelle facture'; ?>
<div class="row"><div class="col-lg-8 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-receipt"></i> Nouvelle facture</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('accounting/invoices') ?>">
            <div class="row mb-3"><div class="col-md-6"><label class="form-label">Client *</label><select name="client_id" class="form-select" required><option value="">— Sélectionner —</option><?php foreach($clients as $c): ?><option value="<?=$c['id']?>"><?=e($c['prenom'].' '.$c['nom'])?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Réservation (optionnel)</label><select name="reservation_id" class="form-select"><option value="">— Aucune —</option><?php foreach($reservations as $r): ?><option value="<?=$r['id']?>">#<?=$r['id']?> — <?=formatDate($r['date_checkin'])?></option><?php endforeach; ?></select></div></div>
            <div class="row mb-3"><div class="col-md-4"><label class="form-label">Date émission *</label><input type="date" name="date_emission" class="form-control" value="<?=date('Y-m-d')?>" required></div><div class="col-md-4"><label class="form-label">Date échéance</label><input type="date" name="date_echeance" class="form-control"></div><div class="col-md-4"><label class="form-label">Taxe applicable</label><select name="tax_id" class="form-select"><option value="">— Aucune —</option><?php foreach($taxes as $t): ?><option value="<?=$t['id']?>"><?=e($t['nom'])?> (<?=$t['taux']?>%)</option><?php endforeach; ?></select></div></div>
            <hr><h6>Lignes de facturation</h6>
            <div id="invoiceLines">
                <div class="row g-2 mb-2 line-item">
                    <div class="col-md-5"><input type="text" name="descriptions[]" class="form-control" placeholder="Description"></div>
                    <div class="col-md-2"><input type="number" name="quantites[]" class="form-control" value="1" min="1"></div>
                    <div class="col-md-3"><input type="number" step="0.01" name="prix_unitaires[]" class="form-control" placeholder="Prix unit."></div>
                    <div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-sm remove-line"><i class="bi bi-x"></i></button></div>
                </div>
            </div>
            <button type="button" class="btn btn-outline-light btn-sm mb-3" id="addLine"><i class="bi bi-plus"></i> Ajouter une ligne</button>
            <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Générer la facture</button>
            <a href="<?= url('accounting/invoices') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
<script>
document.getElementById('addLine').addEventListener('click', function(){
    var div = document.createElement('div');
    div.className = 'row g-2 mb-2 line-item';
    div.innerHTML = '<div class="col-md-5"><input type="text" name="descriptions[]" class="form-control" placeholder="Description"></div><div class="col-md-2"><input type="number" name="quantites[]" class="form-control" value="1" min="1"></div><div class="col-md-3"><input type="number" step="0.01" name="prix_unitaires[]" class="form-control" placeholder="Prix unit."></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-sm remove-line"><i class="bi bi-x"></i></button></div>';
    document.getElementById('invoiceLines').appendChild(div);
    div.querySelector('.remove-line').addEventListener('click', function(){ div.remove(); });
});
document.querySelectorAll('.remove-line').forEach(b => b.addEventListener('click', function(){ this.closest('.line-item').remove(); }));
</script>
