<?php $title = 'Employés'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-person-badge"></i> Employés</h4>
    <a href="<?= url('hr/employees/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvel employé</a>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table table-hover mb-0">
        <thead><tr><th>Nom</th><th>Prénom</th><th>Poste</th><th>Département</th><th>Contrat</th><th>Salaire</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($employees as $e): ?>
            <tr>
                <td><?= e($e['nom']) ?></td><td><?= e($e['prenom']) ?></td>
                <td><?= e($e['poste']) ?></td><td><?= e($e['department_nom']) ?></td>
                <td><span class="badge bg-info"><?= $e['type_contrat'] ?></span></td>
                <td><?= formatMoney($e['salaire_base']) ?></td>
                <td><span class="badge bg-<?= $e['statut']==='actif'?'success':($e['statut']==='inactif'?'secondary':'danger') ?>"><?= $e['statut'] ?></span></td>
                <td>
                    <a href="<?= url('hr/employees/show/' . $e['id']) ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></a>
                    <a href="<?= url('hr/employees/edit/' . $e['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <a href="<?= url('hr/employees/delete/' . $e['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
