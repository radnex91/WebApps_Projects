<?php
$title = 'Nouvelle évaluation';
$activeMenu = 'evaluations';
$breadcrumbs = ['Évaluations' => '/evaluations', 'Nouvelle évaluation' => null];
?>

<div class="row">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-plus mr-1"></i> Nouvelle évaluation
                </h3>
            </div>
            <form action="<?php echo APP_URL; ?>/evaluations/store" method="post">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="evaluation_period_id">Période d'évaluation <span class="text-danger">*</span></label>
                                <select name="evaluation_period_id" id="evaluation_period_id" class="form-control custom-select" required>
                                    <option value="">-- Sélectionner --</option>
                                    <?php if (!empty($periods)): ?>
                                        <?php foreach ($periods as $period): ?>
                                            <option value="<?php echo e($period['id']); ?>">
                                                <?php echo e($period['name']); ?>
                                                (<?php echo formatDate($period['start_date']); ?> - <?php echo formatDate($period['end_date']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="employee_id">Employé <span class="text-danger">*</span></label>
                                <select name="employee_id" id="employee_id" class="form-control select2" data-placeholder="Sélectionner un employé" required>
                                    <option value="">-- Sélectionner --</option>
                                    <?php if (!empty($employees)): ?>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?php echo e($emp['id']); ?>">
                                                <?php echo e(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($criteria)): ?>
                        <h6 class="mt-4 mb-3"><i class="fas fa-list-check mr-1"></i> Critères d'évaluation</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Critère</th>
                                        <th style="width:120px;">Score (1-5)</th>
                                        <th>Commentaire</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($criteria as $c): ?>
                                        <tr>
                                            <td>
                                                <?php echo e($c['name']); ?>
                                                <br><small class="text-muted">Poids : <?php echo e($c['weight']); ?> | Max : <?php echo e($c['max_score']); ?></small>
                                            </td>
                                            <td>
                                                <input type="number" name="criteria[<?php echo e($c['id']); ?>]"
                                                       class="form-control form-control-sm" min="1" max="5"
                                                       placeholder="1-5">
                                            </td>
                                            <td>
                                                <input type="text" name="criteria_comments[<?php echo e($c['id']); ?>]"
                                                       class="form-control form-control-sm" placeholder="Commentaire">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <div class="form-group mt-3">
                        <label for="comments">Commentaires généraux</label>
                        <textarea name="comments" id="comments" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="recommendations">Recommandations</label>
                        <textarea name="recommendations" id="recommendations" class="form-control" rows="3"></textarea>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Enregistrer
                    </button>
                    <a href="<?php echo APP_URL; ?>/evaluations" class="btn btn-default">
                        <i class="fas fa-times mr-1"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>