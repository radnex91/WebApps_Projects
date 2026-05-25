<?php
$title = 'Détail de l\'évaluation';
$activeMenu = 'evaluations';
$breadcrumbs = ['Évaluations' => '/evaluations', 'Détail' => null];
?>

<div class="row">
    <!-- Evaluation Info -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle mr-1"></i> Informations
                </h3>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th>Employé</th>
                        <td><?php echo e(($evaluation['first_name'] ?? '') . ' ' . ($evaluation['last_name'] ?? '')); ?></td>
                    </tr>
                    <tr>
                        <th>Période</th>
                        <td><?php echo e($evaluation['period_name'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <th>Évaluateur</th>
                        <td><?php echo e($evaluation['evaluator_name'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <th>Statut</th>
                        <td><?php echo getStatusBadge($evaluation['status']); ?></td>
                    </tr>
                </table>

                <!-- Overall Score -->
                <div class="text-center mt-3 mb-2">
                    <h6>Score global</h6>
                    <?php $score = $evaluation['overall_score'] ?? 0; ?>
                    <?php $scoreColor = $score >= 4 ? 'success' : ($score >= 3 ? 'warning' : ($score >= 2 ? 'orange' : 'danger')); ?>
                    <div class="d-flex justify-content-center align-items-center">
                        <span class="display-4 text-<?php echo $scoreColor; ?>" style="font-weight:700;">
                            <?php echo number_format($score, 2); ?>
                        </span>
                        <span class="text-muted ml-1" style="font-size:1.2rem;">/5</span>
                    </div>
                </div>
            </div>
            <?php if (($evaluation['status'] ?? '') !== 'complété'): ?>
                <div class="card-footer text-center">
                    <a href="<?php echo APP_URL; ?>/evaluations/<?php echo e($evaluation['id']); ?>/evaluate"
                       class="btn btn-warning btn-sm">
                        <i class="fas fa-star mr-1"></i> Évaluer
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Criteria Results -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list-check mr-1"></i> Résultats par critère
                </h3>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($results)): ?>
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Critère</th>
                                <th>Catégorie</th>
                                <th>Score / Max</th>
                                <th>Poids</th>
                                <th>Commentaire</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $r): ?>
                                <tr>
                                    <td><?php echo e($r['criteria_name']); ?></td>
                                    <td><span class="badge badge-light"><?php echo e($r['category']); ?></span></td>
                                    <td>
                                        <?php
                                        $ratio = ($r['max_score'] ?? 5) > 0 ? $r['score'] / $r['max_score'] : 0;
                                        $barColor = $ratio >= 0.8 ? 'success' : ($ratio >= 0.6 ? 'warning' : 'danger');
                                        ?>
                                        <div class="d-flex align-items-center">
                                            <span class="mr-2 font-weight-bold"><?php echo (int)$r['score']; ?>/<?php echo (int)($r['max_score'] ?? 5); ?></span>
                                            <div class="progress flex-grow-1" style="height:8px;">
                                                <div class="progress-bar bg-<?php echo $barColor; ?>"
                                                     style="width:<?php echo min(100, $ratio * 100); ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo e($r['weight']); ?></td>
                                    <td><?php echo e($r['comment'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-muted py-4">Aucun critère évalué pour le moment.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Comments & Recommendations -->
        <div class="row mt-3">
            <?php if (!empty($evaluation['comments'])): ?>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-comment mr-1"></i> Commentaires</h3>
                        </div>
                        <div class="card-body">
                            <?php echo nl2br(e($evaluation['comments'])); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($evaluation['recommendations'])): ?>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-lightbulb mr-1"></i> Recommandations</h3>
                        </div>
                        <div class="card-body">
                            <?php echo nl2br(e($evaluation['recommendations'])); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>