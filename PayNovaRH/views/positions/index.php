<?php
$pageTitle = 'Postes';
$activeMenu = 'positions';
$breadcrumbs = ['Postes' => ''];
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-briefcase mr-1"></i> Liste des postes
                    </h3>
                    <?php if (Auth::hasPermission('positions', 'create')): ?>
                        <a href="<?php echo APP_URL; ?>/positions/create" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus mr-1"></i> Nouveau poste
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="positionsTable" class="table table-bordered table-striped table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Titre</th>
                                <th>Département</th>
                                <th class="text-right">Salaire Min</th>
                                <th class="text-right">Salaire Max</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($positions)): ?>
                                <?php foreach ($positions as $pos): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-briefcase mr-1 text-muted"></i>
                                            <?php echo e($pos['title']); ?>
                                        </td>
                                        <td><?php echo e($pos['department_name'] ?? '-'); ?></td>
                                        <td class="text-right"><?php echo formatMoney($pos['salary_min'] ?? 0); ?></td>
                                        <td class="text-right"><?php echo formatMoney($pos['salary_max'] ?? 0); ?></td>
                                        <td class="text-center align-middle">
                                            <?php if (Auth::hasPermission('positions', 'edit')): ?>
                                                <a href="<?php echo APP_URL; ?>/positions/<?php echo (int)$pos['id']; ?>/edit"
                                                   class="btn btn-warning btn-xs" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (Auth::hasPermission('positions', 'delete')): ?>
                                                <button type="button"
                                                        class="btn btn-danger btn-xs btn-delete"
                                                        data-id="<?php echo (int)$pos['id']; ?>"
                                                        data-name="<?php echo e($pos['title']); ?>"
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
                                        Aucun poste trouvé.
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
            html: 'Voulez-vous vraiment supprimer le poste <strong>' + name + '</strong> ?<br>Cette action est irréversible.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then(function (result) {
            if (result.isConfirmed) {
                $.post('<?php echo APP_URL; ?>/positions/' + id + '/delete', {
                    _token: '<?php echo Session::csrfToken(); ?>',
                    _method: 'DELETE'
                }, function () {
                    Toast.fire({ icon: 'success', title: 'Poste supprimé avec succès' });
                    location.reload();
                }).fail(function () {
                    Toast.fire({ icon: 'error', title: 'Erreur lors de la suppression' });
                });
            }
        });
    });
});
</script>