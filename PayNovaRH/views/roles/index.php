<?php
$title = 'Rôles & Permissions';
$activeMenu = 'roles';
$breadcrumbs = ['Rôles & Permissions' => null];
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-lock mr-1"></i> Rôles
                </h3>
                <div class="card-tools">
                    <a href="<?php echo APP_URL; ?>/roles/create" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus mr-1"></i> Nouveau rôle
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table id="roles-table" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Description</th>
                            <th>Utilisateurs</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($roles)): ?>
                            <?php foreach ($roles as $role): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo e($role['name']); ?></strong>
                                    </td>
                                    <td><?php echo e($role['description'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo (int)($role['user_count'] ?? 0); ?></span>
                                    </td>
                                    <td>
                                        <a href="<?php echo APP_URL; ?>/roles/<?php echo e($role['id']); ?>/edit" class="btn btn-warning btn-xs" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if (!in_array($role['name'] ?? '', ['admin', 'manager', 'employé'])): ?>
                                            <button type="button" class="btn btn-danger btn-xs btn-delete"
                                                    data-url="<?php echo APP_URL; ?>/roles/<?php echo e($role['id']); ?>/delete"
                                                    title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">Aucun rôle trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    $('#roles-table').DataTable();

    $('.btn-delete').on('click', function () {
        var url = $(this).data('url');
        Swal.fire({
            title: 'Confirmer la suppression ?',
            text: 'Ce rôle sera définitivement supprimé.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Supprimer',
            cancelButtonText: 'Annuler'
        }).then(function (result) {
            if (result.isConfirmed) {
                $.post(url, function () {
                    Toast.fire({ icon: 'success', title: 'Rôle supprimé.' });
                    location.reload();
                }).fail(function (xhr) {
                    Toast.fire({ icon: 'error', title: 'Impossible de supprimer ce rôle.' });
                });
            }
        });
    });
});
</script>