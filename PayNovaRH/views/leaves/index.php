<?php
$pageTitle = 'Congés';
$activeMenu = 'leaves';
$breadcrumbs = ['Congés' => ''];
?>
<div class="row mb-3">
    <div class="col-md-6">
        <h3>Congés</h3>
    </div>
    <div class="col-md-6 text-right">
        <?php if (Auth::hasPermission('leaves', 'create')): ?>
        <a href="<?php echo APP_URL; ?>/leaves/create" class="btn btn-primary">
            <i class="fas fa-plus mr-1"></i> Nouvelle demande
        </a>
        <?php endif; ?>
        <a href="<?php echo APP_URL; ?>/leaves/balances" class="btn btn-info ml-2">
            <i class="fas fa-balance-scale mr-1"></i> Soldes
        </a>
        <a href="<?php echo APP_URL; ?>/leaves/calendar" class="btn btn-secondary ml-2">
            <i class="fas fa-calendar mr-1"></i> Calendrier
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-bordered table-striped dataTable">
            <thead>
                <tr>
                    <th>Employé</th>
                    <th>Type</th>
                    <th>Début</th>
                    <th>Fin</th>
                    <th>Jours</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaves as $leave): ?>
                <tr>
                    <td>
                        <?php if (!empty($leave['photo'])): ?>
                            <img src="<?php echo APP_URL; ?>/uploads/<?php echo e($leave['photo']); ?>" class="employee-photo mr-2" alt="">
                        <?php endif; ?>
                        <?php echo e($leave['first_name'] . ' ' . $leave['last_name']); ?>
                    </td>
                    <td><?php echo e($leave['leave_type']); ?></td>
                    <td><?php echo formatDate($leave['start_date']); ?></td>
                    <td><?php echo formatDate($leave['end_date']); ?></td>
                    <td><span class="font-weight-bold"><?php echo e($leave['total_days']); ?></span></td>
                    <td><?php echo getStatusBadge($leave['status']); ?></td>
                    <td>
                        <a href="<?php echo APP_URL; ?>/leaves/<?php echo $leave['id']; ?>" class="btn btn-sm btn-info" title="Voir">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if (Auth::hasPermission('leaves', 'approve') && $leave['status'] === 'en_attente'): ?>
                        <form method="POST" action="<?php echo APP_URL; ?>/leaves/<?php echo $leave['id']; ?>/approve" style="display:inline">
                            <button type="submit" class="btn btn-sm btn-success" title="Approuver"><i class="fas fa-check"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>