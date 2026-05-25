<?php
$title = 'Nouvelle formation';
$activeMenu = 'training';
$breadcrumbs = ['Formations' => '/training', 'Nouvelle formation' => null];
?>

<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-plus mr-1"></i> Nouvelle formation
                </h3>
            </div>
            <form action="<?php echo APP_URL; ?>/training/store" method="post">
                <div class="card-body">
                    <div class="form-group">
                        <label for="title">Titre <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="title" class="form-control"
                               value="<?php echo e($old['title'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"><?php echo e($old['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="trainer">Formateur</label>
                        <input type="text" name="trainer" id="trainer" class="form-control"
                               value="<?php echo e($old['trainer'] ?? ''); ?>" placeholder="Nom du formateur">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="start_date">Date de début</label>
                                <input type="date" name="start_date" id="start_date" class="form-control"
                                       value="<?php echo e($old['start_date'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="end_date">Date de fin</label>
                                <input type="date" name="end_date" id="end_date" class="form-control"
                                       value="<?php echo e($old['end_date'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="location">Lieu</label>
                                <input type="text" name="location" id="location" class="form-control"
                                       value="<?php echo e($old['location'] ?? ''); ?>" placeholder="Salle / Adresse">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="capacity">Capacité</label>
                                <input type="number" name="capacity" id="capacity" class="form-control"
                                       value="<?php echo e($old['capacity'] ?? ''); ?>" min="1" placeholder="Nombre de places">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="budget">Budget (MAD)</label>
                                <input type="number" name="budget" id="budget" class="form-control"
                                       value="<?php echo e($old['budget'] ?? ''); ?>" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="status">Statut</label>
                                <select name="status" id="status" class="form-control custom-select">
                                    <option value="planifié" <?php echo (isset($old['status']) && $old['status'] === 'planifié') ? 'selected' : ''; ?>>Planifié</option>
                                    <option value="en_cours" <?php echo (isset($old['status']) && $old['status'] === 'en_cours') ? 'selected' : ''; ?>>En cours</option>
                                    <option value="terminé" <?php echo (isset($old['status']) && $old['status'] === 'terminé') ? 'selected' : ''; ?>>Terminé</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Enregistrer
                    </button>
                    <a href="<?php echo APP_URL; ?>/training" class="btn btn-default">
                        <i class="fas fa-times mr-1"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>