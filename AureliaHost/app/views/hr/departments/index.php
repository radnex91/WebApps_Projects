<?php $title = 'Départements'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-diagram-3"></i> Départements</h4>
    <a href="<?= url('hr/departments/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouveau</a>
</div>
<div class="row g-3">
    <?php foreach ($departments as $d): ?>
    <div class="col-md-4 col-lg-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-building fs-1 text-primary"></i>
                <h5 class="mt-2"><?= e($d['nom']) ?></h5>
                <span class="badge bg-info"><?= $d['nb_employes'] ?> employé(s)</span>
                <?php if ($d['description']): ?><p class="text-muted small mt-2"><?= e($d['description']) ?></p><?php endif; ?>
            </div>
            <div class="card-footer d-flex justify-content-center gap-2">
                <a href="<?= url('hr/departments/edit/' . $d['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <a href="<?= url('hr/departments/delete/' . $d['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
