<?php
$title = 'Recrutement';
$activeMenu = 'recruitment';
$breadcrumbs = ['Recrutement' => null];
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-bullhorn mr-1"></i> Offres d'emploi
                </h3>
                <div class="card-tools">
                    <a href="<?php echo APP_URL; ?>/recruitment/create" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus mr-1"></i> Nouvelle offre
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table id="recruitment-table" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Département</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Candidatures</th>
                            <th>Date limite</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($jobs)): ?>
                            <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo APP_URL; ?>/recruitment/<?php echo e($job['id']); ?>">
                                            <?php echo e($job['title']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo e($job['department_name'] ?? 'Non défini'); ?></td>
                                    <td><?php echo e($job['type']); ?></td>
                                    <td><?php echo getStatusBadge($job['status']); ?></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo (int)($job['application_count'] ?? 0); ?></span>
                                    </td>
                                    <td><?php echo formatDate($job['deadline']); ?></td>
                                    <td>
                                        <a href="<?php echo APP_URL; ?>/recruitment/<?php echo e($job['id']); ?>" class="btn btn-info btn-xs" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo APP_URL; ?>/recruitment/<?php echo e($job['id']); ?>/edit" class="btn btn-warning btn-xs" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger btn-xs btn-delete"
                                                data-url="<?php echo APP_URL; ?>/recruitment/<?php echo e($job['id']); ?>/delete"
                                                title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Aucune offre d'emploi trouvée.</td>
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
    $('#recruitment-table').DataTable();

    $('.btn-delete').on('click', function () {
        var url = $(this).data('url');
        Swal.fire({
            title: 'Confirmer la suppression ?',
            text: 'Cette action est irréversible.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Supprimer',
            cancelButtonText: 'Annuler'
        }).then(function (result) {
            if (result.isConfirmed) {
                $.post(url, function () {
                    Toast.fire({ icon: 'success', title: 'Offre supprimée.' });
                    location.reload();
                });
            }
        });
    });
});
</script>