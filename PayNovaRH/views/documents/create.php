<?php
$title = 'Ajouter un document';
$activeMenu = 'documents';
$breadcrumbs = ['Documents' => '/documents', 'Ajouter' => null];
?>

<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-upload mr-1"></i> Ajouter un document
                </h3>
            </div>
            <form action="<?php echo APP_URL; ?>/documents/store" method="post" enctype="multipart/form-data">
                <div class="card-body">
                    <div class="form-group">
                        <label for="employee_id">Employé <span class="text-danger">*</span></label>
                        <select name="employee_id" id="employee_id" class="form-control select2" data-placeholder="Sélectionner un employé" required>
                            <option value="">-- Sélectionner --</option>
                            <?php if (!empty($employees)): ?>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo e($emp['id']); ?>" <?php echo (isset($old['employee_id']) && $old['employee_id'] == $emp['id']) ? 'selected' : ''; ?>>
                                        <?php echo e(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="title">Titre <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="title" class="form-control"
                               value="<?php echo e($old['title'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="category">Catégorie</label>
                        <select name="category" id="category" class="form-control custom-select">
                            <option value="contrat" <?php echo (isset($old['category']) && $old['category'] === 'contrat') ? 'selected' : ''; ?>>Contrat</option>
                            <option value="paie" <?php echo (isset($old['category']) && $old['category'] === 'paie') ? 'selected' : ''; ?>>Paie</option>
                            <option value="formation" <?php echo (isset($old['category']) && $old['category'] === 'formation') ? 'selected' : ''; ?>>Formation</option>
                            <option value="évaluation" <?php echo (isset($old['category']) && $old['category'] === 'évaluation') ? 'selected' : ''; ?>>Évaluation</option>
                            <option value="autre" <?php echo (isset($old['category']) && $old['category'] === 'autre') ? 'selected' : ''; ?>>Autre</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"><?php echo e($old['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="file">Fichier <span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" name="file" id="file" class="custom-file-input" required>
                            <label class="custom-file-label" data-browse="Parcourir">Choisir un fichier</label>
                        </div>
                        <small class="form-text text-muted">Formats acceptés : PDF, DOC, DOCX, XLS, XLSX, JPG, PNG.</small>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload mr-1"></i> Téléverser
                    </button>
                    <a href="<?php echo APP_URL; ?>/documents" class="btn btn-default">
                        <i class="fas fa-times mr-1"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function () {
    $('.custom-file-input').on('change', function () {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName || 'Choisir un fichier');
    });
});
</script>