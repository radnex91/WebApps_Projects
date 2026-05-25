<?php $title = 'Chambres'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-door-open"></i> Chambres</h4>
    <a href="<?= url('hotel/rooms/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvelle chambre</a>
</div>
<div class="row g-3">
    <?php foreach ($rooms as $r): ?>
    <div class="col-md-4 col-lg-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <span class="fs-1">
                    <?php if ($r['statut'] === 'disponible'): ?><i class="bi bi-check-circle text-success"></i>
                    <?php elseif ($r['statut'] === 'occupee'): ?><i class="bi bi-x-circle text-danger"></i>
                    <?php elseif ($r['statut'] === 'maintenance'): ?><i class="bi bi-tools text-warning"></i>
                    <?php else: ?><i class="bi bi-droplet text-info"></i><?php endif; ?>
                </span>
                <h5 class="mt-2">Chambre <?= e($r['numero']) ?></h5>
                <div class="text-muted small"><?= e($r['type_nom'] ?? 'N/A') ?> — <?= formatMoney($r['prix_base'] ?? 0) ?></div>
                <div class="text-muted small">Étage <?= $r['etage'] ?></div>
                <span class="badge bg-<?= $r['statut']==='disponible'?'success':($r['statut']==='occupee'?'danger':($r['statut']==='maintenance'?'warning':'info')) ?> mt-2"><?= $r['statut'] ?></span>
            </div>
            <div class="card-footer d-flex justify-content-center gap-2">
                <a href="<?= url('hotel/rooms/show/' . $r['id']) ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></a>
                <a href="<?= url('hotel/rooms/edit/' . $r['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <a href="<?= url('hotel/rooms/delete/' . $r['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
