<?php $title = 'Congés'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-calendar-heart"></i> Congés</h4>
    <a href="<?= url('hr/leaves/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvelle demande</a>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table table-hover mb-0">
        <thead><tr><th>Employé</th><th>Type</th><th>Du</th><th>Au</th><th>Statut</th><th>Approuvé par</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($leaves as $l): ?>
            <tr>
                <td><?= e($l['prenom'].' '.$l['nom']) ?></td>
                <td><span class="badge bg-secondary"><?= str_replace('_',' ',$l['type']) ?></span></td>
                <td><?= formatDate($l['date_debut']) ?></td><td><?= formatDate($l['date_fin']) ?></td>
                <td><span class="badge bg-<?= $l['statut']==='approuve'?'success':($l['statut']==='refuse'?'danger':'warning') ?>"><?= $l['statut'] ?></span></td>
                <td><?= e(($l['approver_prenom']??'').' '.($l['approver_nom']??'')) ?: '—' ?></td>
                <td>
                    <?php if ($l['statut'] === 'en_attente'): ?>
                    <a href="<?= url('hr/leaves/approve/' . $l['id']) ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-check-lg"></i></a>
                    <a href="<?= url('hr/leaves/reject/' . $l['id']) ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                    <a href="<?= url('hr/leaves/delete/' . $l['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
