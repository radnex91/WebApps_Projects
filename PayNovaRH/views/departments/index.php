<?php
$pageTitle = 'Départements';
$activeMenu = 'departments';
$breadcrumbs = ['Départements' => ''];
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-building mr-1"></i> Liste des départements
                    </h3>
                    <?php if (Auth::hasPermission('departments', 'create')): ?>
                        <a href="<?php echo APP_URL; ?>/departments/create" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus mr-1"></i> Nouveau département
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="departmentsTable" class="table table-bordered table-striped table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Manager</th>
                                <th class="text-center">Nb Employés</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($departments)): ?>
                                <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/departments/<?php echo (int)$dept['id']; ?>">
                                                <i class="fas fa-building mr-1 text-muted"></i>
                                                <?php echo e($dept['name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo e($dept['description'] ?? '-'); ?></td>
                                        <td><?php echo e($dept['manager_name'] ?? '-'); ?></td>
                                        <td class="text-center">
                                            <span class="badge badge-primary"><?php echo (int)($dept['employee_count'] ?? 0); ?></span>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?php if (Auth::hasPermission('departments', 'edit')): ?>
                                                <a href="<?php echo APP_URL; ?>/departments/<?php echo (int)$dept['id']; ?>/edit"
                                                   class="btn btn-warning btn-xs" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (Auth::hasPermission('departments', 'delete')): ?>
                                                <button type="button"
                                                        class="btn btn-danger btn-xs btn-delete"
                                                        data-id="<?php echo (int)$dept['id']; ?>"
                                                        data-name="<?php echo e($dept['name']); ?>"
                                                        title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        Aucun département trouvé.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    $('.btn-delete').on('click', function () {
        var id = $(this).data('id');
        var name = $(this).data('name');
        Swal.fire({
            title: 'Confirmer la suppression',
            html: 'Voulez-vous vraiment supprimer le département <strong>' + name + '</strong> ?<br>Cette action est irréversible.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then(function (result) {
            if (result.isConfirmed) {
                $.post('<?php echo APP_URL; ?>/departments/' + id + '/delete', {
                    _token: '<?php echo Session::csrfToken(); ?>',
                    _method: 'DELETE'
                }, function () {
                    Toast.fire({ icon: 'success', title: 'Département supprimé avec succès' });
                    location.reload();
                }).fail(function () {
                    Toast.fire({ icon: 'error', title: 'Erreur lors de la suppression' });
                });
            }
        });
    });
});
</script>