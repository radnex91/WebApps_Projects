<?php
$title = 'Nouveau rôle';
$activeMenu = 'roles';
$breadcrumbs = ['Rôles & Permissions' => '/roles', 'Nouveau rôle' => null];
?>

<div class="row">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-plus mr-1"></i> Nouveau rôle
                </h3>
            </div>
            <form action="<?php echo APP_URL; ?>/roles/store" method="post">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">Nom du rôle <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="name" class="form-control"
                                       value="<?php echo e($old['name'] ?? ''); ?>" required
                                       placeholder="Ex : superviseur">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <input type="text" name="description" id="description" class="form-control"
                                       value="<?php echo e($old['description'] ?? ''); ?>"
                                       placeholder="Description du rôle">
                            </div>
                        </div>
                    </div>

                    <!-- Permissions Grid -->
                    <h6 class="mt-4 mb-3"><i class="fas fa-key mr-1"></i> Permissions</h6>
                    <?php if (!empty($groupedPermissions)): ?>
                        <?php foreach ($groupedPermissions as $module => $perms): ?>
                            <div class="card mb-2">
                                <div class="card-header py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input select-all-module"
                                                   id="module-<?php echo e($module); ?>"
                                                   data-module="<?php echo e($module); ?>">
                                            <label class="custom-control-label font-weight-bold text-capitalize"
                                                   for="module-<?php echo e($module); ?>">
                                                <?php echo e($module); ?>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body py-2">
                                    <div class="row">
                                        <?php foreach ($perms as $perm): ?>
                                            <div class="col-lg-3 col-md-4 col-sm-6">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input perm-checkbox"
                                                           name="permissions[]"
                                                           id="perm-<?php echo e($perm['id']); ?>"
                                                           value="<?php echo e($perm['id']); ?>"
                                                           data-module="<?php echo e($module); ?>">
                                                    <label class="custom-control-label"
                                                           for="perm-<?php echo e($perm['id']); ?>">
                                                        <?php echo e($perm['action']); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Aucune permission disponible.</p>
                    <?php endif; ?>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Enregistrer
                    </button>
                    <a href="<?php echo APP_URL; ?>/roles" class="btn btn-default">
                        <i class="fas fa-times mr-1"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function () {
    // Select all for module
    $('.select-all-module').on('change', function () {
        var module = $(this).data('module');
        var checked = $(this).is(':checked');
        $('.perm-checkbox[data-module="' + module + '"]').prop('checked', checked);
    });

    // Update select-all state when individual perm changes
    $('.perm-checkbox').on('change', function () {
        var module = $(this).data('module');
        var total = $('.perm-checkbox[data-module="' + module + '"]').length;
        var checked = $('.perm-checkbox[data-module="' + module + '"]:checked').length;
        $('#module-' + module).prop('checked', total === checked);
        $('#module-' + module).prop('indeterminate', checked > 0 && checked < total);
    });
});
</script>