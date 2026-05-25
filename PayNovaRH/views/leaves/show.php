<?php
$pageTitle = 'Détail du congé';
$activeMenu = 'leaves';
$breadcrumbs = ['Congés' => '/leaves', 'Détail' => ''];
?>
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-navy">
                <h3 class="card-title">Détail du congé</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr><th width="200">Employé</th><td><?php echo e($leave['first_name'] . ' ' . $leave['last_name']); ?></td></tr>
                    <tr><th>Type de congé</th><td><?php echo e($leave['leave_type']); ?> (<?php echo $leave['is_paid'] ? 'Payé' : 'Non payé'; ?>)</td></tr>
                    <tr><th>Date début</th><td><?php echo formatDate($leave['start_date']); ?></td></tr>
                    <tr><th>Date fin</th><td><?php echo formatDate($leave['end_date']); ?></td></tr>
                    <tr><th>Nombre de jours</th><td><span class="font-weight-bold text-primary"><?php echo e($leave['total_days']); ?> jour(s)</span></td></tr>
                    <tr><th>Motif</th><td><?php echo e($leave['reason'] ?? 'Aucun motif'); ?></td></tr>
                    <tr><th>Statut</th><td><?php echo getStatusBadge($leave['status']); ?></td></tr>
                    <?php if ($leave['approved_by']): ?>
                    <tr><th>Approuvé par</th><td><?php echo e($leave['approver_name'] ?? ''); ?> - <?php echo formatDateTime($leave['approved_at']); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($leave['rejection_reason']): ?>
                    <tr><th>Motif de refus</th><td class="text-danger"><?php echo e($leave['rejection_reason']); ?></td></tr>
                    <?php endif; ?>
                </table>

                <?php if ($leave['status'] === 'en_attente' && Auth::hasPermission('leaves', 'approve')): ?>
                <div class="mt-3">
                    <form method="POST" action="<?php echo APP_URL; ?>/leaves/<?php echo $leave['id']; ?>/approve" style="display:inline">
                        <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i> Approuver</button>
                    </form>
                    <button type="button" class="btn btn-danger ml-2" data-toggle="modal" data-target="#rejectModal">
                        <i class="fas fa-times mr-1"></i> Refuser
                    </button>
                </div>

                <div class="modal fade" id="rejectModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="<?php echo APP_URL; ?>/leaves/<?php echo $leave['id']; ?>/reject">
                                <div class="modal-header"><h5 class="modal-title">Motif du refus</h5></div>
                                <div class="modal-body">
                                    <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                                    <button type="submit" class="btn btn-danger">Confirmer le refus</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($leave['status'] === 'en_attente'): ?>
                <form method="POST" action="<?php echo APP_URL; ?>/leaves/<?php echo $leave['id']; ?>/cancel" style="display:inline" class="mt-2">
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Annuler ce congé ?')">
                        <i class="fas fa-ban mr-1"></i> Annuler
                    </button>
                </form>
                <?php endif; ?>

                <a href="<?php echo APP_URL; ?>/leaves" class="btn btn-secondary ml-2"><i class="fas fa-arrow-left mr-1"></i> Retour</a>
            </div>
        </div>
    </div>

    <?php if ($balance): ?>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-info">
                <h3 class="card-title">Solde de congé</h3>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>Total</span><span class="font-weight-bold"><?php echo $balance['total_days']; ?> jours</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>Utilisé</span><span class="text-danger font-weight-bold"><?php echo $balance['used_days']; ?> jours</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span>Restant</span><span class="text-success font-weight-bold"><?php echo $balance['remaining_days']; ?> jours</span>
                </div>
                <div class="progress mt-3" style="height:25px">
                    <div class="progress-bar bg-danger" style="width:<?php echo $balance['total_days'] > 0 ? ($balance['used_days']/$balance['total_days']*100) : 0; ?>%">
                        Utilisé
                    </div>
                    <div class="progress-bar bg-success" style="width:<?php echo $balance['total_days'] > 0 ? ($balance['remaining_days']/$balance['total_days']*100) : 100; ?>%">
                        Restant
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>