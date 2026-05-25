<?php
$title = 'Formations';
$activeMenu = 'training';
$breadcrumbs = ['Formations' => null];
?>

<div class="row mb-3">
    <div class="col-12 text-right">
        <a href="<?php echo APP_URL; ?>/training/create" class="btn btn-primary btn-sm">
            <i class="fas fa-plus mr-1"></i> Nouvelle formation
        </a>
    </div>
</div>

<div class="row">
    <?php if (!empty($programs)): ?>
        <?php foreach ($programs as $program): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title text-truncate" style="max-width:200px;" title="<?php echo e($program['title']); ?>">
                            <?php echo e($program['title']); ?>
                        </h3>
                        <div class="card-tools">
                            <?php echo getStatusBadge($program['status']); ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-2">
                            <i class="fas fa-chalkboard-teacher mr-1"></i>
                            <strong>Formateur :</strong> <?php echo e($program['trainer'] ?? 'Non défini'); ?>
                        </p>
                        <p class="text-muted small mb-2">
                            <i class="fas fa-calendar mr-1"></i>
                            <?php echo formatDate($program['start_date']); ?> -
                            <?php echo formatDate($program['end_date']); ?>
                        </p>
                        <p class="text-muted small mb-2">
                            <i class="fas fa-map-marker-alt mr-1"></i>
                            <?php echo e($program['location'] ?? 'Non défini'); ?>
                        </p>
                        <p class="text-muted small mb-2">
                            <i class="fas fa-users mr-1"></i>
                            <strong>Inscrits :</strong>
                            <?php echo (int)($program['enrolled_count'] ?? 0); ?> /
                            <?php echo (int)($program['capacity'] ?? 0); ?>
                        </p>
                        <p class="text-muted small mb-0">
                            <i class="fas fa-money-bill mr-1"></i>
                            <strong>Budget :</strong>
                            <?php echo !empty($program['budget']) ? formatMoney($program['budget']) : 'Non défini'; ?>
                        </p>
                    </div>
                    <div class="card-footer text-center">
                        <a href="<?php echo APP_URL; ?>/training/<?php echo e($program['id']); ?>" class="btn btn-info btn-sm">
                            <i class="fas fa-eye mr-1"></i> Voir
                        </a>
                        <a href="<?php echo APP_URL; ?>/training/<?php echo e($program['id']); ?>/edit" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit mr-1"></i> Modifier
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-1"></i> Aucune formation trouvée.
            </div>
        </div>
    <?php endif; ?>
</div>