<?php
$pageTitle = 'Modifier le contrat';
$activeMenu = 'contracts';
$breadcrumbs = ['Contrats' => '/contracts', 'Contrat #' . $contract['id'] => '/contracts/' . $contract['id'], 'Modifier' => ''];
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card card-warning">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-edit mr-1"></i> Modifier le contrat
                </h3>
            </div>
            <form action="<?php echo APP_URL; ?>/contracts/<?php echo (int)$contract['id']; ?>" method="post">
                <input type="hidden" name="_method" value="PUT">
                
                    <?php echo Session::csrfField(); ?>

                <div class="card-body">
                    <div class="form-group">
                        <label for="employee_id">Employé <span class="text-danger">*</span></label>
                        <select name="employee_id"
                                id="employee_id"
                                class="form-control select2<?php echo isset($errors['employee_id']) ? ' is-invalid' : ''; ?>"
                                data-placeholder="Sélectionner un employé"
                                required>
                            <option value=""></option>
                            <?php if (!empty($employees)): ?>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo (int)$emp['id']; ?>"
                                        <?php echo (($old['employee_id'] ?? $contract['employee_id'] ?? '') == $emp['id']) ? 'selected' : ''; ?>>
                                        <?php echo e(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')); ?>
                                        <?php if (!empty($emp['department_name'])): ?>
                                            (<?php echo e($emp['department_name']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (isset($errors['employee_id'])): ?>
                            <span class="invalid-feedback"><?php echo e($errors['employee_id']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="type">Type de contrat <span class="text-danger">*</span></label>
                        <select name="type"
                                id="type"
                                class="form-control custom-select<?php echo isset($errors['type']) ? ' is-invalid' : ''; ?>"
                                required>
                            <option value="">-- Sélectionner --</option>
                            <option value="CDI" <?php echo (($old['type'] ?? $contract['type'] ?? '') === 'CDI') ? 'selected' : ''; ?>>CDI</option>
                            <option value="CDD" <?php echo (($old['type'] ?? $contract['type'] ?? '') === 'CDD') ? 'selected' : ''; ?>>CDD</option>
                            <option value="Stage" <?php echo (($old['type'] ?? $contract['type'] ?? '') === 'Stage') ? 'selected' : ''; ?>>Stage</option>
                            <option value="Freelance" <?php echo (($old['type'] ?? $contract['type'] ?? '') === 'Freelance') ? 'selected' : ''; ?>>Freelance</option>
                            <option value="Intérim" <?php echo (($old['type'] ?? $contract['type'] ?? '') === 'Intérim') ? 'selected' : ''; ?>>Intérim</option>
                        </select>
                        <?php if (isset($errors['type'])): ?>
                            <span class="invalid-feedback"><?php echo e($errors['type']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="start_date">Date de début <span class="text-danger">*</span></label>
                                <input type="date"
                                       name="start_date"
                                       id="start_date"
                                       class="form-control<?php echo isset($errors['start_date']) ? ' is-invalid' : ''; ?>"
                                       value="<?php echo e($old['start_date'] ?? $contract['start_date'] ?? ''); ?>"
                                       required>
                                <?php if (isset($errors['start_date'])): ?>
                                    <span class="invalid-feedback"><?php echo e($errors['start_date']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="end_date">Date de fin</label>
                                <input type="date"
                                       name="end_date"
                                       id="end_date"
                                       class="form-control"
                                       value="<?php echo e($old['end_date'] ?? $contract['end_date'] ?? ''); ?>">
                                <small class="form-text text-muted">Laisser vide pour un contrat à durée indéterminée (CDI).</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="salary">Salaire (MAD) <span class="text-danger">*</span></label>
                                <input type="number"
                                       name="salary"
                                       id="salary"
                                       class="form-control<?php echo isset($errors['salary']) ? ' is-invalid' : ''; ?>"
                                       value="<?php echo e($old['salary'] ?? $contract['salary'] ?? ''); ?>"
                                       min="0"
                                       step="0.01"
                                       placeholder="0.00"
                                       required>
                                <?php if (isset($errors['salary'])): ?>
                                    <span class="invalid-feedback"><?php echo e($errors['salary']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="renewal_date">Date de renouvellement</label>
                                <input type="date"
                                       name="renewal_date"
                                       id="renewal_date"
                                       class="form-control"
                                       value="<?php echo e($old['renewal_date'] ?? $contract['renewal_date'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description"
                                  id="description"
                                  class="form-control"
                                  rows="3"
                                  placeholder="Conditions particulières, notes..."><?php echo e($old['description'] ?? $contract['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox"
                                   name="is_active"
                                   id="is_active"
                                   class="custom-control-input"
                                   value="1"
                                   <?php echo (!isset($old['is_active']) && ($contract['is_active'] ?? $contract['status'] ?? 'actif') !== 'inactif')
                                       || (isset($old['is_active']) && $old['is_active']) ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="is_active">Contrat actif</label>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Mettre à jour
                    </button>
                    <a href="<?php echo APP_URL; ?>/contracts" class="btn btn-secondary ml-2">
                        <i class="fas fa-times mr-1"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>