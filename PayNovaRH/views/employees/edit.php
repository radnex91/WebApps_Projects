<?php
$pageTitle = 'Modifier l\'employé';
$activeMenu = 'employees';
$breadcrumbs = ['Employés' => '/employees', $employee['first_name'] . ' ' . $employee['last_name'] => '/employees/' . $employee['id'], 'Modifier' => ''];
?>

<div class="row">
    <div class="col-12">
        <form action="<?php echo APP_URL; ?>/employees/<?php echo (int)$employee['id']; ?>" method="post" enctype="multipart/form-data" id="employeeForm">
            <input type="hidden" name="_method" value="PUT">
            
                <?php echo Session::csrfField(); ?>

            <!-- Informations personnelles & professionnelles -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-user mr-1"></i> Informations personnelles
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="first_name">Prénom <span class="text-danger">*</span></label>
                                        <input type="text"
                                               name="first_name"
                                               id="first_name"
                                               class="form-control<?php echo isset($errors['first_name']) ? ' is-invalid' : ''; ?>"
                                               value="<?php echo e($old['first_name'] ?? $employee['first_name'] ?? ''); ?>"
                                               required>
                                        <?php if (isset($errors['first_name'])): ?>
                                            <span class="invalid-feedback"><?php echo e($errors['first_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="last_name">Nom <span class="text-danger">*</span></label>
                                        <input type="text"
                                               name="last_name"
                                               id="last_name"
                                               class="form-control<?php echo isset($errors['last_name']) ? ' is-invalid' : ''; ?>"
                                               value="<?php echo e($old['last_name'] ?? $employee['last_name'] ?? ''); ?>"
                                               required>
                                        <?php if (isset($errors['last_name'])): ?>
                                            <span class="invalid-feedback"><?php echo e($errors['last_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email">Email <span class="text-danger">*</span></label>
                                        <input type="email"
                                               name="email"
                                               id="email"
                                               class="form-control<?php echo isset($errors['email']) ? ' is-invalid' : ''; ?>"
                                               value="<?php echo e($old['email'] ?? $employee['email'] ?? ''); ?>"
                                               required>
                                        <?php if (isset($errors['email'])): ?>
                                            <span class="invalid-feedback"><?php echo e($errors['email']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="phone">Téléphone</label>
                                        <input type="tel"
                                               name="phone"
                                               id="phone"
                                               class="form-control"
                                               value="<?php echo e($old['phone'] ?? $employee['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="address">Adresse</label>
                                <input type="text"
                                       name="address"
                                       id="address"
                                       class="form-control"
                                       value="<?php echo e($old['address'] ?? $employee['address'] ?? ''); ?>">
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="city">Ville</label>
                                        <input type="text"
                                               name="city"
                                               id="city"
                                               class="form-control"
                                               value="<?php echo e($old['city'] ?? $employee['city'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="country">Pays</label>
                                        <input type="text"
                                               name="country"
                                               id="country"
                                               class="form-control"
                                               value="<?php echo e($old['country'] ?? $employee['country'] ?? 'Maroc'); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="birth_date">Date de naissance</label>
                                        <input type="date"
                                               name="birth_date"
                                               id="birth_date"
                                               class="form-control"
                                               value="<?php echo e($old['birth_date'] ?? $employee['birth_date'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="gender">Genre</label>
                                        <select name="gender" id="gender" class="form-control custom-select">
                                            <option value="">-- Sélectionner --</option>
                                            <option value="homme" <?php echo (($old['gender'] ?? $employee['gender'] ?? '') === 'homme') ? 'selected' : ''; ?>>Homme</option>
                                            <option value="femme" <?php echo (($old['gender'] ?? $employee['gender'] ?? '') === 'femme') ? 'selected' : ''; ?>>Femme</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="marital_status">Situation matrimoniale</label>
                                        <select name="marital_status" id="marital_status" class="form-control custom-select">
                                            <option value="">-- Sélectionner --</option>
                                            <option value="célibataire" <?php echo (($old['marital_status'] ?? $employee['marital_status'] ?? '') === 'célibataire') ? 'selected' : ''; ?>>Célibataire</option>
                                            <option value="marié" <?php echo (($old['marital_status'] ?? $employee['marital_status'] ?? '') === 'marié') ? 'selected' : ''; ?>>Marié(e)</option>
                                            <option value="divorcé" <?php echo (($old['marital_status'] ?? $employee['marital_status'] ?? '') === 'divorcé') ? 'selected' : ''; ?>>Divorcé(e)</option>
                                            <option value="veuf" <?php echo (($old['marital_status'] ?? $employee['marital_status'] ?? '') === 'veuf') ? 'selected' : ''; ?>>Veuf/Veuve</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="children_count">Nombre d'enfants</label>
                                        <input type="number"
                                               name="children_count"
                                               id="children_count"
                                               class="form-control"
                                               min="0"
                                               value="<?php echo e($old['children_count'] ?? $employee['children_count'] ?? '0'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="cin">CIN</label>
                                        <input type="text"
                                               name="cin"
                                               id="cin"
                                               class="form-control"
                                               value="<?php echo e($old['cin'] ?? $employee['cin'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="cnss">CNSS</label>
                                <input type="text"
                                       name="cnss"
                                       id="cnss"
                                       class="form-control"
                                       value="<?php echo e($old['cnss'] ?? $employee['cnss'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-briefcase mr-1"></i> Informations professionnelles
                            </h3>
                        </div>
                        <div class="card-body">
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
                                                <?php echo (($old['department_id'] ?? $employee['department_id'] ?? '') == $dept['id']) ? 'selected' : ''; ?>>
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
                                <label for="position_id">Poste <span class="text-danger">*</span></label>
                                <select name="position_id"
                                        id="position_id"
                                        class="form-control select2<?php echo isset($errors['position_id']) ? ' is-invalid' : ''; ?>"
                                        data-placeholder="Sélectionner un poste"
                                        required>
                                    <option value=""></option>
                                    <?php if (!empty($positions)): ?>
                                        <?php foreach ($positions as $pos): ?>
                                            <option value="<?php echo (int)$pos['id']; ?>"
                                                <?php echo (($old['position_id'] ?? $employee['position_id'] ?? '') == $pos['id']) ? 'selected' : ''; ?>>
                                                <?php echo e($pos['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <?php if (isset($errors['position_id'])): ?>
                                    <span class="invalid-feedback"><?php echo e($errors['position_id']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="hire_date">Date d'embauche <span class="text-danger">*</span></label>
                                <input type="date"
                                       name="hire_date"
                                       id="hire_date"
                                       class="form-control<?php echo isset($errors['hire_date']) ? ' is-invalid' : ''; ?>"
                                       value="<?php echo e($old['hire_date'] ?? $employee['hire_date'] ?? ''); ?>"
                                       required>
                                <?php if (isset($errors['hire_date'])): ?>
                                    <span class="invalid-feedback"><?php echo e($errors['hire_date']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="status">Statut</label>
                                <select name="status" id="status" class="form-control custom-select">
                                    <option value="actif" <?php echo (($old['status'] ?? $employee['status'] ?? '') === 'actif') ? 'selected' : ''; ?>>Actif</option>
                                    <option value="inactif" <?php echo (($old['status'] ?? $employee['status'] ?? '') === 'inactif') ? 'selected' : ''; ?>>Inactif</option>
                                    <option value="résilié" <?php echo (($old['status'] ?? $employee['status'] ?? '') === 'résilié') ? 'selected' : ''; ?>>Résilié</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="photo">Photo</label>
                                <?php if (!empty($employee['photo'])): ?>
                                    <div class="mb-2">
                                        <img src="<?php echo APP_URL; ?>/uploads/<?php echo e($employee['photo']); ?>"
                                             class="img-thumbnail"
                                             alt="Photo actuelle"
                                             style="max-width:100px;max-height:100px;">
                                    </div>
                                <?php endif; ?>
                                <div class="custom-file">
                                    <input type="file"
                                           name="photo"
                                           id="photo"
                                           class="custom-file-input"
                                           accept="image/jpeg,image/png,image/gif">
                                    <label class="custom-file-label" for="photo" data-browse="Parcourir">
                                        Choisir une photo...
                                    </label>
                                </div>
                                <small class="form-text text-muted">Laisser vide pour conserver la photo actuelle.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact d'urgence -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card card-warning">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-phone-alt mr-1"></i> Contact d'urgence
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="emergency_name">Nom du contact</label>
                                <input type="text"
                                       name="emergency_name"
                                       id="emergency_name"
                                       class="form-control"
                                       value="<?php echo e($old['emergency_name'] ?? $employee['emergency_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="emergency_relationship">Lien de parenté</label>
                                <input type="text"
                                       name="emergency_relationship"
                                       id="emergency_relationship"
                                       class="form-control"
                                       value="<?php echo e($old['emergency_relationship'] ?? $employee['emergency_relationship'] ?? ''); ?>"
                                       placeholder="Ex : Père, Mère, Conjoint...">
                            </div>
                            <div class="form-group">
                                <label for="emergency_phone">Téléphone du contact</label>
                                <input type="tel"
                                       name="emergency_phone"
                                       id="emergency_phone"
                                       class="form-control"
                                       value="<?php echo e($old['emergency_phone'] ?? $employee['emergency_phone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informations bancaires -->
                <div class="col-md-6">
                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-university mr-1"></i> Informations bancaires
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="bank_name">Banque</label>
                                <input type="text"
                                       name="bank_name"
                                       id="bank_name"
                                       class="form-control"
                                       value="<?php echo e($old['bank_name'] ?? $employee['bank_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="bank_rib">RIB</label>
                                <input type="text"
                                       name="bank_rib"
                                       id="bank_rib"
                                       class="form-control"
                                       value="<?php echo e($old['bank_rib'] ?? $employee['bank_rib'] ?? ''); ?>"
                                       maxlength="24"
                                       placeholder="24 chiffres">
                            </div>
                            <div class="form-group">
                                <label for="bank_account_holder">Titulaire du compte</label>
                                <input type="text"
                                       name="bank_account_holder"
                                       id="bank_account_holder"
                                       class="form-control"
                                       value="<?php echo e($old['bank_account_holder'] ?? $employee['bank_account_holder'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Boutons d'action -->
            <div class="row">
                <div class="col-12">
                    <div class="mt-3 mb-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Mettre à jour
                        </button>
                        <a href="<?php echo APP_URL; ?>/employees/<?php echo (int)$employee['id']; ?>" class="btn btn-info ml-2">
                            <i class="fas fa-eye mr-1"></i> Voir la fiche
                        </a>
                        <a href="<?php echo APP_URL; ?>/employees" class="btn btn-secondary ml-2">
                            <i class="fas fa-times mr-1"></i> Annuler
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(function () {
    // Custom file input label
    $('.custom-file-input').on('change', function () {
        var fileName = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').addClass('selected').html(fileName || 'Choisir une photo...');
    });
});
</script>