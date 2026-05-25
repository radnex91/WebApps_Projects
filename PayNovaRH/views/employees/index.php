<?php
$pageTitle = 'Employés';
$activeMenu = 'employees';
$breadcrumbs = ['Employés' => ''];
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-users mr-1"></i> Liste des employés
                    </h3>
                    <?php if (Auth::hasPermission('employees', 'create')): ?>
                        <a href="<?php echo APP_URL; ?>/employees/create" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus mr-1"></i> Nouvel employé
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <!-- Search & Filters -->
                <form method="get" action="<?php echo APP_URL; ?>/employees" class="mb-3">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="input-group input-group-sm">
                                <input type="text"
                                       name="search"
                                       class="form-control"
                                       placeholder="Rechercher un employé..."
                                       value="<?php echo e($search ?? ''); ?>">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select name="department" class="form-control form-control-sm custom-select">
                                <option value="">-- Département --</option>
                                <?php if (!empty($departments)): ?>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo e($dept['id']); ?>"
                                            <?php echo (isset($filterDepartment) && $filterDepartment == $dept['id']) ? 'selected' : ''; ?>>
                                            <?php echo e($dept['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-control form-control-sm custom-select">
                                <option value="">-- Statut --</option>
                                <option value="actif" <?php echo (isset($filterStatus) && $filterStatus === 'actif') ? 'selected' : ''; ?>>Actif</option>
                                <option value="inactif" <?php echo (isset($filterStatus) && $filterStatus === 'inactif') ? 'selected' : ''; ?>>Inactif</option>
                                <option value="résilié" <?php echo (isset($filterStatus) && $filterStatus === 'résilié') ? 'selected' : ''; ?>>Résilié</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-outline-secondary btn-sm btn-block">
                                <i class="fas fa-filter mr-1"></i> Filtrer
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Employees Table -->
                <div class="table-responsive">
                    <table id="employeesTable" class="table table-bordered table-striped table-hover dt-responsive">
                        <thead class="thead-dark">
                            <tr>
                                <th width="50">Photo</th>
                                <th>Nom complet</th>
                                <th>Email</th>
                                <th>Département</th>
                                <th>Poste</th>
                                <th>Statut</th>
                                <th>Date d'embauche</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($employees)): ?>
                                <?php foreach ($employees as $emp): ?>
                                    <tr>
                                        <td class="text-center align-middle">
                                            <?php if (!empty($emp['photo'])): ?>
                                                <img src="<?php echo APP_URL; ?>/uploads/<?php echo e($emp['photo']); ?>"
                                                     class="img-circle"
                                                     alt="Photo"
                                                     style="width:35px;height:35px;">
                                            <?php else: ?>
                                                <div class="mx-auto bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center"
                                                     style="width:35px;height:35px;font-size:14px;">
                                                    <?php echo e(mb_strtoupper(mb_substr($emp['first_name'] ?? 'U', 0, 1))); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/employees/<?php echo (int)$emp['id']; ?>">
                                                <?php echo e(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')); ?>
                                            </a>
                                        </td>
                                        <td><?php echo e($emp['email'] ?? ''); ?></td>
                                        <td><?php echo e($emp['department_name'] ?? '-'); ?></td>
                                        <td><?php echo e($emp['position_title'] ?? '-'); ?></td>
                                        <td><?php echo getStatusBadge($emp['status'] ?? ''); ?></td>
                                        <td><?php echo formatDate($emp['hire_date'] ?? null); ?></td>
                                        <td class="text-center align-middle">
                                            <?php if (Auth::hasPermission('employees', 'view')): ?>
                                                <a href="<?php echo APP_URL; ?>/employees/<?php echo (int)$emp['id']; ?>"
                                                   class="btn btn-info btn-xs" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (Auth::hasPermission('employees', 'edit')): ?>
                                                <a href="<?php echo APP_URL; ?>/employees/<?php echo (int)$emp['id']; ?>/edit"
                                                   class="btn btn-warning btn-xs" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (Auth::hasPermission('employees', 'delete')): ?>
                                                <button type="button"
                                                        class="btn btn-danger btn-xs btn-delete"
                                                        data-id="<?php echo (int)$emp['id']; ?>"
                                                        data-name="<?php echo e(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')); ?>"
                                                        title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        Aucun employé trouvé.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if (!empty($pagination) && $pagination['total_pages'] > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <small class="text-muted">
                            Affichage de <?php echo (int)$pagination['from']; ?> à <?php echo (int)$pagination['to']; ?>
                            sur <?php echo (int)$pagination['total']; ?> employé(s)
                        </small>
                        <nav aria-label="Pagination">
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($pagination['current_page'] > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link"
                                           href="<?php echo APP_URL; ?>/employees?page=<?php echo (int)($pagination['current_page'] - 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="fas fa-chevron-left"></i></span>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $pagination['current_page'] ? 'active' : ''; ?>">
                                        <a class="page-link"
                                           href="<?php echo APP_URL; ?>/employees?page=<?php echo (int)$i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                            <?php echo (int)$i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                                    <li class="page-item">
                                        <a class="page-link"
                                           href="<?php echo APP_URL; ?>/employees?page=<?php echo (int)($pagination['current_page'] + 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="fas fa-chevron-right"></i></span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    // Delete confirmation
    $('.btn-delete').on('click', function () {
        var id = $(this).data('id');
        var name = $(this).data('name');
        Swal.fire({
            title: 'Confirmer la suppression',
            html: 'Voulez-vous vraiment supprimer l\'employé <strong>' + name + '</strong> ?<br>Cette action est irréversible.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then(function (result) {
            if (result.isConfirmed) {
                $.post('<?php echo APP_URL; ?>/employees/' + id + '/delete', {
                    _token: '<?php echo Session::csrfToken(); ?>',
                    _method: 'DELETE'
                }, function () {
                    Toast.fire({ icon: 'success', title: 'Employé supprimé avec succès' });
                    location.reload();
                }).fail(function () {
                    Toast.fire({ icon: 'error', title: 'Erreur lors de la suppression' });
                });
            }
        });
    });
});
</script>