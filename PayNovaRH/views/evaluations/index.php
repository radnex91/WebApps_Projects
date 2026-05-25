<?php
$title = 'Évaluations';
$activeMenu = 'evaluations';
$breadcrumbs = ['Évaluations' => null];
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-star mr-1"></i> Liste des évaluations
                </h3>
                <div class="card-tools">
                    <a href="<?php echo APP_URL; ?>/evaluations/create" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus mr-1"></i> Nouvelle évaluation
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($periods)): ?>
                    <div class="form-group mb-3" style="max-width:300px;">
                        <label for="filter_period">Filtrer par période</label>
                        <select id="filter_period" class="form-control custom-select">
                            <option value="">Toutes les périodes</option>
                            <?php foreach ($periods as $period): ?>
                                <option value="<?php echo e($period['id']); ?>">
                                    <?php echo e($period['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <table id="evaluations-table" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Période</th>
                            <th>Évaluateur</th>
                            <th>Score</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($evaluations)): ?>
                            <?php foreach ($evaluations as $eval): ?>
                                <tr data-period="<?php echo e($eval['evaluation_period_id']); ?>">
                                    <td>
                                        <?php echo e(($eval['first_name'] ?? '') . ' ' . ($eval['last_name'] ?? '')); ?>
                                    </td>
                                    <td><?php echo e($eval['period_name'] ?? ''); ?></td>
                                    <td><?php echo e($eval['evaluator_name'] ?? ''); ?></td>
                                    <td>
                                        <?php if (isset($eval['overall_score'])): ?>
                                            <span class="badge badge-<?php echo $eval['overall_score'] >= 4 ? 'success' : ($eval['overall_score'] >= 3 ? 'warning' : 'danger'); ?>">
                                                <?php echo number_format($eval['overall_score'], 2); ?>/5
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo getStatusBadge($eval['status']); ?></td>
                                    <td>
                                        <a href="<?php echo APP_URL; ?>/evaluations/<?php echo e($eval['id']); ?>" class="btn btn-info btn-xs" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (($eval['status'] ?? '') !== 'complété'): ?>
                                            <a href="<?php echo APP_URL; ?>/evaluations/<?php echo e($eval['id']); ?>/evaluate" class="btn btn-warning btn-xs" title="Évaluer">
                                                <i class="fas fa-star"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Aucune évaluation trouvée.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    $('#evaluations-table').DataTable();

    $('#filter_period').on('change', function () {
        var periodId = $(this).val();
        $('#evaluations-table tbody tr').each(function () {
            if (!periodId || $(this).data('period') == periodId || $(this).find('td').attr('colspan')) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
});
</script>