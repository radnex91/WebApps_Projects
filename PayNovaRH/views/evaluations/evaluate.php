<?php
$title = 'Évaluer';
$activeMenu = 'evaluations';
$breadcrumbs = ['Évaluations' => '/evaluations', 'Évaluer' => null];
?>

<div class="row">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-star mr-1"></i> Formulaire d'évaluation
                </h3>
            </div>
            <form action="<?php echo APP_URL; ?>/evaluations/<?php echo e($evaluation['id']); ?>/complete" method="post" id="evaluate-form">
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-1"></i>
                        Attribuez un score de 1 à 5 pour chaque critère. 1 = Très insuffisant, 5 = Excellent.
                    </div>

                    <?php if (!empty($results)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width:25%;">Critère</th>
                                        <th>Catégorie</th>
                                        <th style="width:30%;">Score (1-5)</th>
                                        <th style="width:25%;">Commentaire</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $r): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo e($r['criteria_name']); ?></strong>
                                                <br><small class="text-muted">Poids : <?php echo e($r['weight']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge badge-light"><?php echo e($r['category']); ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <div class="form-check form-check-inline mr-2">
                                                            <input type="radio"
                                                                   name="criteria[<?php echo e($r['criteria_id']); ?>]"
                                                                   id="score_<?php echo e($r['criteria_id']); ?>_<?php echo $i; ?>"
                                                                   value="<?php echo $i; ?>"
                                                                   class="form-check-input score-radio"
                                                                   <?php echo (isset($r['score']) && (int)$r['score'] === $i) ? 'checked' : ''; ?>
                                                                   required>
                                                            <label class="form-check-label"
                                                                   for="score_<?php echo e($r['criteria_id']); ?>_<?php echo $i; ?>">
                                                                <?php echo $i; ?>
                                                            </label>
                                                        </div>
                                                    <?php endfor; ?>
                                                </div>
                                                <input type="hidden" name="criteria_ids[]" value="<?php echo e($r['criteria_id']); ?>">
                                            </td>
                                            <td>
                                                <input type="text" name="criteria_comments[<?php echo e($r['criteria_id']); ?>]"
                                                       class="form-control form-control-sm"
                                                       value="<?php echo e($r['comment'] ?? ''); ?>"
                                                       placeholder="Commentaire">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif (!empty($criteria)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width:25%;">Critère</th>
                                        <th>Catégorie</th>
                                        <th style="width:30%;">Score (1-5)</th>
                                        <th style="width:25%;">Commentaire</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($criteria as $c): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo e($c['name']); ?></strong>
                                                <br><small class="text-muted">Poids : <?php echo e($c['weight']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge badge-light"><?php echo e($c['category']); ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <div class="form-check form-check-inline mr-2">
                                                            <input type="radio"
                                                                   name="criteria[<?php echo e($c['id']); ?>]"
                                                                   id="score_<?php echo e($c['id']); ?>_<?php echo $i; ?>"
                                                                   value="<?php echo $i; ?>"
                                                                   class="form-check-input score-radio" required>
                                                            <label class="form-check-label"
                                                                   for="score_<?php echo e($c['id']); ?>_<?php echo $i; ?>">
                                                                <?php echo $i; ?>
                                                            </label>
                                                        </div>
                                                    <?php endfor; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="text" name="criteria_comments[<?php echo e($c['id']); ?>]"
                                                       class="form-control form-control-sm"
                                                       placeholder="Commentaire">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted py-4">Aucun critère disponible pour l'évaluation.</p>
                    <?php endif; ?>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check mr-1"></i> Valider l'évaluation
                    </button>
                    <a href="<?php echo APP_URL; ?>/evaluations" class="btn btn-default">
                        <i class="fas fa-times mr-1"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function () {
    // Highlight selected score label
    $('.score-radio').on('change', function () {
        var name = $(this).attr('name');
        $('input[name="' + name + '"]').closest('.form-check').removeClass('text-primary font-weight-bold');
        $(this).closest('.form-check').addClass('text-primary font-weight-bold');
    }).filter(':checked').trigger('change');
});
</script>