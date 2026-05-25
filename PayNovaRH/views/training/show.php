<?php
$title = e($program['title']);
$activeMenu = 'training';
$breadcrumbs = ['Formations' => '/training', e($program['title']) => null];
?>

<div class="row">
    <!-- Program Details -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-graduation-cap mr-1"></i> Détails de la formation
                </h3>
                <div class="card-tools">
                    <a href="<?php echo APP_URL; ?>/training/<?php echo e($program['id']); ?>/edit" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit mr-1"></i> Modifier
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th style="width:140px;">Titre</th>
                        <td><?php echo e($program['title']); ?></td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td><?php echo nl2br(e($program['description'] ?? '')); ?></td>
                    </tr>
                    <tr>
                        <th>Formateur</th>
                        <td><?php echo e($program['trainer'] ?? 'Non défini'); ?></td>
                    </tr>
                    <tr>
                        <th>Date de début</th>
                        <td><?php echo formatDate($program['start_date']); ?></td>
                    </tr>
                    <tr>
                        <th>Date de fin</th>
                        <td><?php echo formatDate($program['end_date']); ?></td>
                    </tr>
                    <tr>
                        <th>Lieu</th>
                        <td><?php echo e($program['location'] ?? 'Non défini'); ?></td>
                    </tr>
                    <tr>
                        <th>Capacité</th>
                        <td><?php echo (int)($program['capacity'] ?? 0); ?> places</td>
                    </tr>
                    <tr>
                        <th>Budget</th>
                        <td><?php echo !empty($program['budget']) ? formatMoney($program['budget']) : 'Non défini'; ?></td>
                    </tr>
                    <tr>
                        <th>Statut</th>
                        <td><?php echo getStatusBadge($program['status']); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Enroll Employee -->
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-plus mr-1"></i> Inscrire un employé
                </h3>
            </div>
            <form action="<?php echo APP_URL; ?>/training/<?php echo e($program['id']); ?>/enroll" method="post">
                <div class="card-body">
                    <div class="form-group">
                        <label for="employee_id">Employé</label>
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
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-block btn-sm">
                        <i class="fas fa-user-plus mr-1"></i> Inscrire
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Enrolled Employees -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-users mr-1"></i> Employés inscrits (<?php echo count($enrollments ?? []); ?>)
                </h3>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($enrollments)): ?>
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Statut</th>
                                <th>Score</th>
                                <th>Feedback</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrollments as $enr): ?>
                                <tr>
                                    <td><?php echo e(($enr['first_name'] ?? '') . ' ' . ($enr['last_name'] ?? '')); ?></td>
                                    <td><?php echo getStatusBadge($enr['status']); ?></td>
                                    <td>
                                        <?php if (isset($enr['score']) && $enr['score'] > 0): ?>
                                            <span class="badge badge-<?php echo $enr['score'] >= 80 ? 'success' : ($enr['score'] >= 50 ? 'warning' : 'danger'); ?>">
                                                <?php echo number_format($enr['score'], 1); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo e($enr['feedback'] ?? '-'); ?></td>
                                    <td>
                                        <?php if (($enr['status'] ?? '') !== 'terminé'): ?>
                                            <button type="button" class="btn btn-success btn-xs btn-complete"
                                                    data-id="<?php echo e($enr['id']); ?>"
                                                    data-program="<?php echo e($program['id']); ?>"
                                                    title="Marquer comme terminé">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-muted py-4">Aucun employé inscrit pour le moment.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Complete Enrollment Modal -->
<div class="modal fade" id="completeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="complete-form" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Terminer l'inscription</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="program_id" id="modal-program-id">
                    <div class="form-group">
                        <label for="modal-score">Score</label>
                        <input type="number" name="score" id="modal-score" class="form-control" min="0" max="100" step="0.1" placeholder="Score (0-100)">
                    </div>
                    <div class="form-group">
                        <label for="modal-feedback">Feedback</label>
                        <textarea name="feedback" id="modal-feedback" class="form-control" rows="3" placeholder="Commentaires sur la formation"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Valider</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function () {
    $('.btn-complete').on('click', function () {
        var id = $(this).data('id');
        var programId = $(this).data('program');
        $('#complete-form').attr('action', '<?php echo APP_URL; ?>/training/enrollment/' + id + '/complete');
        $('#modal-program-id').val(programId);
        $('#completeModal').modal('show');
    });
});
</script>