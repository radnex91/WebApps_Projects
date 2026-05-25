<?php $title = 'Entretien'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-droplet"></i> Entretien des chambres</h4>
    <a href="<?= url('hotel/housekeeping/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvelle tâche</a>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table table-hover mb-0">
        <thead><tr><th>Chambre</th><th>Date</th><th>Assigné à</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($tasks as $t): ?>
            <tr>
                <td>Ch. <?= e($t['numero']) ?></td>
                <td><?= formatDate($t['date_nettoyage']) ?></td>
                <td><?= e(($t['user_prenom'] ?? '') . ' ' . ($t['user_nom'] ?? '')) ?: '—' ?></td>
                <td><span class="badge bg-<?= $t['statut']==='planifie'?'warning':($t['statut']==='en_cours'?'info':'success') ?>"><?= $t['statut'] ?></span></td>
                <td>
                    <a href="<?= url('hotel/housekeeping/edit/' . $t['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <a href="<?= url('hotel/housekeeping/delete/' . $t['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
