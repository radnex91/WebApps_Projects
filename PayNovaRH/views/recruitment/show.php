<?php
$title = e($job['title']);
$activeMenu = 'recruitment';
$breadcrumbs = ['Recrutement' => '/recruitment', e($job['title']) => null];
?>

<div class="row">
    <!-- Job Details -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-bullhorn mr-1"></i> Détails de l'offre
                </h3>
                <div class="card-tools">
                    <a href="<?php echo APP_URL; ?>/recruitment/<?php echo e($job['id']); ?>/edit" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit mr-1"></i> Modifier
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th style="width:180px;">Titre</th>
                        <td><?php echo e($job['title']); ?></td>
                    </tr>
                    <tr>
                        <th>Département</th>
                        <td><?php echo e($job['department_name'] ?? 'Non défini'); ?></td>
                    </tr>
                    <tr>
                        <th>Poste</th>
                        <td><?php echo e($job['position_title'] ?? 'Non défini'); ?></td>
                    </tr>
                    <tr>
                        <th>Type</th>
                        <td><?php echo e($job['type']); ?></td>
                    </tr>
                    <tr>
                        <th>Statut</th>
                        <td><?php echo getStatusBadge($job['status']); ?></td>
                    </tr>
                    <tr>
                        <th>Fourchette salariale</th>
                        <td><?php echo e($job['salary_range'] ?? 'Non spécifié'); ?></td>
                    </tr>
                    <tr>
                        <th>Lieu</th>
                        <td><?php echo e($job['location'] ?? 'Non spécifié'); ?></td>
                    </tr>
                    <tr>
                        <th>Date limite</th>
                        <td><?php echo formatDate($job['deadline']); ?></td>
                    </tr>
                    <tr>
                        <th>Créée le</th>
                        <td><?php echo formatDate($job['created_at']); ?></td>
                    </tr>
                </table>

                <hr>

                <h6><i class="fas fa-align-left mr-1"></i> Description</h6>
                <div class="mb-3"><?php echo nl2br(e($job['description'] ?? '')); ?></div>

                <?php if (!empty($job['requirements'])): ?>
                    <h6><i class="fas fa-list-check mr-1"></i> Exigences</h6>
                    <div><?php echo nl2br(e($job['requirements'])); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Apply Form (public) -->
    <div class="col-lg-4">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-paper-plane mr-1"></i> Postuler
                </h3>
            </div>
            <form action="<?php echo APP_URL; ?>/recruitment/apply" method="post" enctype="multipart/form-data">
                <input type="hidden" name="job_posting_id" value="<?php echo e($job['id']); ?>">
                <div class="card-body">
                    <div class="form-group">
                        <label for="first_name">Prénom <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" id="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Nom <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" id="last_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="email">E-mail <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Téléphone</label>
                        <input type="text" name="phone" id="phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="resume">CV <span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" name="resume" id="resume" class="custom-file-input" required>
                            <label class="custom-file-label" data-browse="Parcourir">Choisir un fichier</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="cover_letter">Lettre de motivation</label>
                        <textarea name="cover_letter" id="cover_letter" class="form-control" rows="4"></textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-paper-plane mr-1"></i> Soumettre ma candidature
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Applications Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-users mr-1"></i> Candidatures (<?php echo count($applications ?? []); ?>)
                </h3>
            </div>
            <div class="card-body">
                <?php if (!empty($applications)): ?>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>E-mail</th>
                                <th>Statut</th>
                                <th>Score</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td><?php echo e(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')); ?></td>
                                    <td><?php echo e($app['email'] ?? ''); ?></td>
                                    <td><?php echo getStatusBadge($app['status']); ?></td>
                                    <td>
                                        <?php if (isset($app['score']) && $app['score'] > 0): ?>
                                            <span class="badge badge-<?php echo $app['score'] >= 80 ? 'success' : ($app['score'] >= 50 ? 'warning' : 'danger'); ?>">
                                                <?php echo (int)$app['score']; ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form action="<?php echo APP_URL; ?>/recruitment/application/<?php echo e($app['id']); ?>/status"
                                              method="post" class="form-inline d-inline-block">
                                            <input type="hidden" name="job_posting_id" value="<?php echo e($job['id']); ?>">
                                            <select name="status" class="form-control form-control-sm custom-select mr-1"
                                                    onchange="this.form.submit()">
                                                <option value="nouveau" <?php echo ($app['status'] ?? '') === 'nouveau' ? 'selected' : ''; ?>>Nouveau</option>
                                                <option value="entretien" <?php echo ($app['status'] ?? '') === 'entretien' ? 'selected' : ''; ?>>Entretien</option>
                                                <option value="test" <?php echo ($app['status'] ?? '') === 'test' ? 'selected' : ''; ?>>Test</option>
                                                <option value="offre" <?php echo ($app['status'] ?? '') === 'offre' ? 'selected' : ''; ?>>Offre</option>
                                                <option value="embauché" <?php echo ($app['status'] ?? '') === 'embauché' ? 'selected' : ''; ?>>Embauché</option>
                                                <option value="refusé" <?php echo ($app['status'] ?? '') === 'refusé' ? 'selected' : ''; ?>>Refusé</option>
                                            </select>
                                        </form>
                                        <?php if (!empty($app['resume'])): ?>
                                            <a href="<?php echo APP_URL; ?>/uploads/<?php echo e($app['resume']); ?>"
                                               class="btn btn-info btn-xs" title="Télécharger CV" target="_blank">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-muted py-3">Aucune candidature pour le moment.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>