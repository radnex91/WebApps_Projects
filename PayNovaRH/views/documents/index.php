<?php
$title = 'Documents';
$activeMenu = 'documents';
$breadcrumbs = ['Documents' => null];
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-folder mr-1"></i> Documents
                </h3>
                <div class="card-tools">
                    <a href="<?php echo APP_URL; ?>/documents/create" class="btn btn-primary btn-sm">
                        <i class="fas fa-upload mr-1"></i> Ajouter un document
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <form method="get" action="<?php echo APP_URL; ?>/documents" class="form-inline mb-3">
                    <div class="form-group mr-2">
                        <label for="employee_id" class="mr-1">Employé</label>
                        <select name="employee_id" id="employee_id" class="form-control form-control-sm select2" data-placeholder="Tous les employés">
                            <option value="">Tous</option>
                            <?php if (!empty($employees)): ?>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo e($emp['id']); ?>" <?php echo (isset($filterEmployee) && $filterEmployee == $emp['id']) ? 'selected' : ''; ?>>
                                        <?php echo e(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group mr-2">
                        <label for="category" class="mr-1">Catégorie</label>
                        <select name="category" id="category" class="form-control form-control-sm custom-select">
                            <option value="">Toutes</option>
                            <option value="contrat" <?php echo (isset($filterCategory) && $filterCategory === 'contrat') ? 'selected' : ''; ?>>Contrat</option>
                            <option value="paie" <?php echo (isset($filterCategory) && $filterCategory === 'paie') ? 'selected' : ''; ?>>Paie</option>
                            <option value="formation" <?php echo (isset($filterCategory) && $filterCategory === 'formation') ? 'selected' : ''; ?>>Formation</option>
                            <option value="évaluation" <?php echo (isset($filterCategory) && $filterCategory === 'évaluation') ? 'selected' : ''; ?>>Évaluation</option>
                            <option value="autre" <?php echo (isset($filterCategory) && $filterCategory === 'autre') ? 'selected' : ''; ?>>Autre</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-primary mr-1">
                        <i class="fas fa-filter mr-1"></i> Filtrer
                    </button>
                    <a href="<?php echo APP_URL; ?>/documents" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times mr-1"></i> Réinitialiser
                    </a>
                </form>

                <table id="documents-table" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Catégorie</th>
                            <th>Employé</th>
                            <th>Type fichier</th>
                            <th>Taille</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($documents)): ?>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo APP_URL; ?>/documents/<?php echo e($doc['id']); ?>">
                                            <i class="fas fa-file mr-1 text-muted"></i>
                                            <?php echo e($doc['title']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php
                                        $catColors = ['contrat' => 'primary', 'paie' => 'success', 'formation' => 'info', 'évaluation' => 'warning', 'autre' => 'secondary'];
                                        $catColor = $catColors[$doc['category'] ?? 'autre'] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?php echo $catColor; ?>">
                                            <?php echo e(ucfirst($doc['category'] ?? 'autre')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo e(($doc['first_name'] ?? '') . ' ' . ($doc['last_name'] ?? '')); ?></td>
                                    <td>
                                        <small class="text-muted"><?php echo e($doc['file_type'] ?? '-'); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $size = (int)($doc['file_size'] ?? 0);
                                        if ($size >= 1048576) echo number_format($size / 1048576, 1) . ' Mo';
                                        elseif ($size >= 1024) echo number_format($size / 1024, 1) . ' Ko';
                                        else echo $size . ' o';
                                        ?>
                                    </td>
                                    <td><?php echo formatDate($doc['created_at']); ?></td>
                                    <td>
                                        <a href="<?php echo APP_URL; ?>/documents/<?php echo e($doc['id']); ?>/download"
                                           class="btn btn-info btn-xs" title="Télécharger">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger btn-xs btn-delete"
                                                data-url="<?php echo APP_URL; ?>/documents/<?php echo e($doc['id']); ?>/delete"
                                                title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Aucun document trouvé.</td>
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
    $('#documents-table').DataTable();

    $('.btn-delete').on('click', function () {
        var url = $(this).data('url');
        Swal.fire({
            title: 'Confirmer la suppression ?',
            text: 'Le fichier sera définitivement supprimé.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Supprimer',
            cancelButtonText: 'Annuler'
        }).then(function (result) {
            if (result.isConfirmed) {
                $.post(url, function () {
                    Toast.fire({ icon: 'success', title: 'Document supprimé.' });
                    location.reload();
                });
            }
        });
    });
});
</script>