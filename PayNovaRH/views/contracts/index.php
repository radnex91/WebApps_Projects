<?php
$pageTitle = 'Contrats';
$activeMenu = 'contracts';
$breadcrumbs = ['Contrats' => ''];
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-file-contract mr-1"></i> Liste des contrats
                    </h3>
                    <?php if (Auth::hasPermission('contracts', 'create')): ?>
                        <a href="<?php echo APP_URL; ?>/contracts/create" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus mr-1"></i> Nouveau contrat
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="contractsTable" class="table table-bordered table-striped table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Employé</th>
                                <th>Type</th>
                                <th>Date début</th>
                                <th>Date fin</th>
                                <th class="text-right">Salaire</th>
                                <th>Statut</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($contracts)): ?>
                                <?php foreach ($contracts as $contract): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/employees/<?php echo (int)($contract['employee_id'] ?? ''); ?>">
                                                <?php echo e(($contract['employee_first_name'] ?? '') . ' ' . ($contract['employee_last_name'] ?? '')); ?>
                                            </a>
                                        </td>
                                        <td><?php echo getStatusBadge($contract['type'] ?? ''); ?></td>
                                        <td><?php echo formatDate($contract['start_date'] ?? null); ?></td>
                                        <td><?php echo formatDate($contract['end_date'] ?? null) ?: '<span class="text-muted">Indéfini</span>'; ?></td>
                                        <td class="text-right"><?php echo formatMoney($contract['salary'] ?? 0); ?></td>
                                        <td><?php echo getStatusBadge($contract['status'] ?? ''); ?></td>
                                        <td class="text-center align-middle">
                                            <?php if (Auth::hasPermission('contracts', 'edit')): ?>
                                                <a href="<?php echo APP_URL; ?>/contracts/<?php echo (int)$contract['id']; ?>/edit"
                                                   class="btn btn-warning btn-xs" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (Auth::hasPermission('contracts', 'delete')): ?>
                                                <button type="button"
                                                        class="btn btn-danger btn-xs btn-delete"
                                                        data-id="<?php echo (int)$contract['id']; ?>"
                                                        data-name="le contrat de <?php echo e(($contract['employee_first_name'] ?? '') . ' ' . ($contract['employee_last_name'] ?? '')); ?>"
                                                        title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        Aucun contrat trouvé.
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
            html: 'Voulez-vous vraiment supprimer <strong>' + name + '</strong> ?<br>Cette action est irréversible.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then(function (result) {
            if (result.isConfirmed) {
                $.post('<?php echo APP_URL; ?>/contracts/' + id + '/delete', {
                    _token: '<?php echo Session::csrfToken(); ?>',
                    _method: 'DELETE'
                }, function () {
                    Toast.fire({ icon: 'success', title: 'Contrat supprimé avec succès' });
                    location.reload();
                }).fail(function () {
                    Toast.fire({ icon: 'error', title: 'Erreur lors de la suppression' });
                });
            }
        });
    });
});
</script>