<?php $title = 'Bulletin de paie'; ?>
<div class="row"><div class="col-md-8 mx-auto">
    <div class="card"><div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-cash-stack"></i> Bulletin de paie</h5>
        <button class="btn btn-sm btn-outline-light" onclick="window.print()"><i class="bi bi-printer"></i> Imprimer</button>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <h6>AureliaHost</h6>
                <p class="text-muted small mb-0">123 Avenue Mohammed V<br>Casablanca, Maroc</p>
            </div>
            <div class="col-md-6 text-end">
                <h6>Bulletin N° <?= str_pad($payroll['id'], 5, '0', STR_PAD_LEFT) ?></h6>
                <p class="text-muted small mb-0">Période : <?= formatDate($payroll['mois'], 'F Y') ?></p>
            </div>
        </div>
        <hr>
        <div class="row mb-4">
            <div class="col-md-6">
                <strong>Employé</strong>
                <p><?= e($payroll['prenom'].' '.$payroll['nom']) ?><br><small class="text-muted"><?= e($payroll['poste']) ?> — <?= e($payroll['department_nom']) ?></small></p>
            </div>
            <div class="col-md-6 text-end">
                <strong>Contrat</strong>
                <p><?= strtoupper($payroll['type_contrat']) ?><br><small class="text-muted">Embauché le <?= $payroll['date_embauche'] ? formatDate($payroll['date_embauche']) : '—' ?></small></p>
            </div>
        </div>
        <table class="table table-bordered">
            <thead class="table-dark"><tr><th>Rubrique</th><th class="text-end">Montant</th></tr></thead>
            <tbody>
                <tr><td>Salaire de base</td><td class="text-end"><?= formatMoney($payroll['salaire_base']) ?></td></tr>
                <tr><td>Primes et indemnités</td><td class="text-end"><?= formatMoney($payroll['primes']) ?></td></tr>
                <tr class="table-success"><th>Salaire brut</th><th class="text-end"><?= formatMoney($payroll['salaire_base'] + $payroll['primes']) ?></th></tr>
                <tr><td>Déductions</td><td class="text-end text-danger">- <?= formatMoney($payroll['deductions']) ?></td></tr>
                <tr class="table-primary"><th>Salaire net à payer</th><th class="text-end fs-5"><?= formatMoney($payroll['salaire_net']) ?></th></tr>
            </tbody>
        </table>
        <div class="text-end"><span class="badge bg-<?= $payroll['statut']==='paye'?'success':($payroll['statut']==='genere'?'info':'warning') ?> fs-6">Statut : <?= $payroll['statut'] ?></span></div>
        <a href="<?= url('hr/payroll') ?>" class="btn btn-secondary mt-3">Retour</a>
    </div></div>
</div></div>
