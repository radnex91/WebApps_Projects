<?php
$title = 'Modifier l\'offre';
$activeMenu = 'recruitment';
$breadcrumbs = ['Recrutement' => '/recruitment', 'Modifier' => null];
?>

<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-edit mr-1"></i> Modifier l'offre d'emploi
                </h3>
            </div>
            <form action="<?php echo APP_URL; ?>/recruitment/<?php echo e($job['id']); ?>/update" method="post">
                <div class="card-body">
                    <div class="form-group">
                        <label for="title">Titre de l'offre <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="title" class="form-control"
                               value="<?php echo e($job['title']); ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="department_id">Département</label>
                                <select name="department_id" id="department_id" class="form-control select2" data-placeholder="Sélectionner un département">
                                    <option value="">-- Sélectionner --</option>
                                    <?php if (!empty($departments)): ?>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo e($dept['id']); ?>" <?php echo (isset($job['department_id']) && $job['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                                <?php echo e($dept['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="position_id">Poste</label>
                                <select name="position_id" id="position_id" class="form-control select2" data-placeholder="Sélectionner un poste">
                                    <option value="">-- Sélectionner --</option>
                                    <?php if (!empty($positions)): ?>
                                        <?php foreach ($positions as $pos): ?>
                                            <option value="<?php echo e($pos['id']); ?>" <?php echo (isset($job['position_id']) && $job['position_id'] == $pos['id']) ? 'selected' : ''; ?>>
                                                <?php echo e($pos['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description <span class="text-danger">*</span></label>
                        <textarea name="description" id="description" class="form-control" rows="5" required><?php echo e($job['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="requirements">Exigences</label>
                        <textarea name="requirements" id="requirements" class="form-control" rows="4"><?php echo e($job['requirements'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="salary_range">Fourchette salariale</label>
                                <input type="text" name="salary_range" id="salary_range" class="form-control"
                                       value="<?php echo e($job['salary_range'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="location">Lieu</label>
                                <input type="text" name="location" id="location" class="form-control"
                                       value="<?php echo e($job['location'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="type">Type de contrat</label>
                                <select name="type" id="type" class="form-control custom-select">
                                    <option value="CDI" <?php echo ($job['type'] ?? '') === 'CDI' ? 'selected' : ''; ?>>CDI</option>
                                    <option value="CDD" <?php echo ($job['type'] ?? '') === 'CDD' ? 'selected' : ''; ?>>CDD</option>
                                    <option value="Stage" <?php echo ($job['type'] ?? '') === 'Stage' ? 'selected' : ''; ?>>Stage</option>
                                    <option value="Freelance" <?php echo ($job['type'] ?? '') === 'Freelance' ? 'selected' : ''; ?>>Freelance</option>
                                    <option value="Intérim" <?php echo ($job['type'] ?? '') === 'Intérim' ? 'selected' : ''; ?>>Intérim</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="status">Statut</label>
                                <select name="status" id="status" class="form-control custom-select">
                                    <option value="brouillon" <?php echo ($job['status'] ?? '') === 'brouillon' ? 'selected' : ''; ?>>Brouillon</option>
                                    <option value="publié" <?php echo ($job['status'] ?? '') === 'publié' ? 'selected' : ''; ?>>Publié</option>
                                    <option value="clôturé" <?php echo ($job['status'] ?? '') === 'clôturé' ? 'selected' : ''; ?>>Clôturé</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="deadline">Date limite</label>
                                <input type="date" name="deadline" id="deadline" class="form-control"
                                       value="<?php echo e($job['deadline'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Mettre à jour
                    </button>
                    <a href="<?php echo APP_URL; ?>/recruitment" class="btn btn-default">
                        <i class="fas fa-times mr-1"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>