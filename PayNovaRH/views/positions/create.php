<?php
$pageTitle = 'Nouveau poste';
$activeMenu = 'positions';
$breadcrumbs = ['Postes' => '/positions', 'Nouveau poste' => ''];
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-briefcase mr-1"></i> Créer un poste
                </h3>
            </div>
            <form action="<?php echo APP_URL; ?>/positions" method="post">
                
                    <?php echo Session::csrfField(); ?>

                <div class="card-body">
                    <div class="form-group">
                        <label for="title">Titre du poste <span class="text-danger">*</span></label>
                        <input type="text"
                               name="title"
                               id="title"
                               class="form-control<?php echo isset($errors['title']) ? ' is-invalid' : ''; ?>"
                               value="<?php echo e($old['title'] ?? ''); ?>"
                               placeholder="Ex : Développeur Full Stack"
                               required
                               autofocus>
                        <?php if (isset($errors['title'])): ?>
                            <span class="invalid-feedback"><?php echo e($errors['title']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="department_id">Département <span class="text-danger">*</span></label>
                        <select name="department_id"
                                id="department_id"
                                class="form-control select2<?php echo isset($errors['department_id']) ? ' is-invalid' : ''; ?>"
                                data-placeholder="Sélectionner un département"
                                required>
                            <option value=""></option>
                            <?php if (!empty($departments)): ?>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo (int)$dept['id']; ?>"
                                        <?php echo (isset($old['department_id']) && $old['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (isset($errors['department_id'])): ?>
                            <span class="invalid-feedback"><?php echo e($errors['department_id']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description"
                                  id="description"
                                  class="form-control"
                                  rows="4"
                                  placeholder="Description du poste, missions principales..."><?php echo e($old['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="salary_min">Salaire minimum (MAD) <span class="text-danger">*</span></label>
                                <input type="number"
                                       name="salary_min"
                                       id="salary_min"
                                       class="form-control<?php echo isset($errors['salary_min']) ? ' is-invalid' : ''; ?>"
                                       value="<?php echo e($old['salary_min'] ?? ''); ?>"
                                       min="0"
                                       step="0.01"
                                       placeholder="0.00"
                                       required>
                                <?php if (isset($errors['salary_min'])): ?>
                                    <span class="invalid-feedback"><?php echo e($errors['salary_min']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="salary_max">Salaire maximum (MAD) <span class="text-danger">*</span></label>
                                <input type="number"
                                       name="salary_max"
                                       id="salary_max"
                                       class="form-control<?php echo isset($errors['salary_max']) ? ' is-invalid' : ''; ?>"
                                       value="<?php echo e($old['salary_max'] ?? ''); ?>"
                                       min="0"
                                       step="0.01"
                                       placeholder="0.00"
                                       required>
                                <?php if (isset($errors['salary_max'])): ?>
                                    <span class="invalid-feedback"><?php echo e($errors['salary_max']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Enregistrer
                    </button>
                    <a href="<?php echo APP_URL; ?>/positions" class="btn btn-secondary ml-2">
                        <i class="fas fa-times mr-1"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>