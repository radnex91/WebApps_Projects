<?php $title = 'Paie'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-cash-stack"></i> Paie</h4>
    <a href="<?= url('hr/payroll/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Générer bulletins</a>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table table-hover mb-0">
        <thead><tr><th>Employé</th><th>Poste</th><th>Mois</th><th>Base</th><th>Primes</th><th>Déduct.</th><th>Net</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($payrolls as $p): ?>
            <tr>
                <td><?= e($p['prenom'].' '.$p['nom']) ?></td><td><?= e($p['poste']) ?></td>
                <td><?= formatDate($p['mois'], 'm/Y') ?></td>
                <td><?= formatMoney($p['salaire_base']) ?></td><td><?= formatMoney($p['primes']) ?></td>
                <td><?= formatMoney($p['deductions']) ?></td><td class="fw-bold"><?= formatMoney($p['salaire_net']) ?></td>
                <td><span class="badge bg-<?= $p['statut']==='paye'?'success':($p['statut']==='genere'?'info':'warning') ?>"><?= $p['statut'] ?></span></td>
                <td>
                    <a href="<?= url('hr/payroll/payslip/' . $p['id']) ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-printer"></i></a>
                    <a href="<?= url('hr/payroll/edit/' . $p['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <a href="<?= url('hr/payroll/delete/' . $p['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
