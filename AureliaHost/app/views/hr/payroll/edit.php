<?php $title = 'Modifier bulletin'; ?>
<div class="row"><div class="col-md-6 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-pencil"></i> Bulletin — <?= e($payroll['prenom'].' '.$payroll['nom']) ?> (<?= formatDate($payroll['mois'], 'm/Y') ?>)</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('hr/payroll/' . $payroll['id']) ?>">
            <div class="mb-3"><label class="form-label">Salaire base (€)</label><input type="number" step="0.01" name="salaire_base" class="form-control" value="<?= $payroll['salaire_base'] ?>"></div>
            <div class="mb-3"><label class="form-label">Primes (€)</label><input type="number" step="0.01" name="primes" class="form-control" value="<?= $payroll['primes'] ?>"></div>
            <div class="mb-3"><label class="form-label">Déductions (€)</label><input type="number" step="0.01" name="deductions" class="form-control" value="<?= $payroll['deductions'] ?>"></div>
            <div class="mb-3"><label class="form-label">Statut</label>
                <select name="statut" class="form-select">
                    <?php foreach(['brouillon','genere','paye'] as $s): ?>
                    <option value="<?=$s?>" <?=$payroll['statut']===$s?'selected':''?>><?=ucfirst($s)?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Mettre à jour</button>
            <a href="<?= url('hr/payroll') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
