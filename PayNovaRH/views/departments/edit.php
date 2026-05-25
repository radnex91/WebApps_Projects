<?php
$pageTitle = 'Modifier le département';
$activeMenu = 'departments';
$breadcrumbs = ['Départements' => '/departments', $department['name'] => '/departments/' . $department['id'], 'Modifier' => ''];
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card card-warning">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-edit mr-1"></i> Modifier le département
                </h3>
            </div>
            <form action="<?php echo APP_URL; ?>/departments/<?php echo (int)$department['id']; ?>" method="post">
                <input type="hidden" name="_method" value="PUT">
                
                    <?php echo Session::csrfField(); ?>

                <div class="card-body">
                    <div class="form-group">
                        <label for="name">Nom du département <span class="text-danger">*</span></label>
                        <input type="text"
                               name="name"
                               id="name"
                               class="form-control<?php echo isset($errors['name']) ? ' is-invalid' : ''; ?>"
                               value="<?php echo e($old['name'] ?? $department['name'] ?? ''); ?>"
                               placeholder="Ex : Ressources Humaines"
                               required
                               autofocus>
                        <?php if (isset($errors['name'])): ?>
                            <span class="invalid-feedback"><?php echo e($errors['name']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description"
                                  id="description"
                                  class="form-control"
                                  rows="3"
                                  placeholder="Description du département..."><?php echo e($old['description'] ?? $department['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="manager_id">Manager</label>
                        <select name="manager_id"
                                id="manager_id"
                                class="form-control select2"
                                data-placeholder="Sélectionner un manager">
                            <option value=""></option>
                            <?php if (!empty($employees)): ?>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo (int)$emp['id']; ?>"
                                        <?php echo (($old['manager_id'] ?? $department['manager_id'] ?? '') == $emp['id']) ? 'selected' : ''; ?>>
                                        <?php echo e(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="parent_id">Département parent</label>
                        <select name="parent_id"
                                id="parent_id"
                                class="form-control select2"
                                data-placeholder="Aucun (département racine)">
                            <option value=""></option>
                            <?php if (!empty($departments)): ?>
                                <?php foreach ($departments as $dept): ?>
                                    <?php if ((int)$dept['id'] !== (int)$department['id']): ?>
                                        <option value="<?php echo (int)$dept['id']; ?>"
                                            <?php echo (($old['parent_id'] ?? $department['parent_id'] ?? '') == $dept['id']) ? 'selected' : ''; ?>>
                                            <?php echo e($dept['name']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Mettre à jour
                    </button>
                    <a href="<?php echo APP_URL; ?>/departments" class="btn btn-secondary ml-2">
                        <i class="fas fa-times mr-1"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>