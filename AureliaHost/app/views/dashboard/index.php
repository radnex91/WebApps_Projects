<?php $title = 'Dashboard'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-speedometer2"></i> Tableau de bord</h4>
    <span class="text-muted"><?= date('l d F Y') ?></span>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4 col-lg-2"><div class="card text-bg-primary"><div class="card-body text-center"><i class="bi bi-door-open fs-2"></i><h5 class="mt-2"><?= $kpis['rooms_total'] ?></h5><small>Chambres</small></div></div></div>
    <div class="col-md-4 col-lg-2"><div class="card text-bg-success"><div class="card-body text-center"><i class="bi bi-check-circle fs-2"></i><h5 class="mt-2"><?= $kpis['rooms_available'] ?></h5><small>Disponibles</small></div></div></div>
    <div class="col-md-4 col-lg-2"><div class="card text-bg-danger"><div class="card-body text-center"><i class="bi bi-x-circle fs-2"></i><h5 class="mt-2"><?= $kpis['rooms_occupied'] ?></h5><small>Occupées</small></div></div></div>
    <div class="col-md-4 col-lg-2"><div class="card text-bg-info"><div class="card-body text-center"><i class="bi bi-calendar-check fs-2"></i><h5 class="mt-2"><?= $kpis['reservations_active'] ?></h5><small>Réserv. actives</small></div></div></div>
    <div class="col-md-4 col-lg-2"><div class="card text-bg-warning"><div class="card-body text-center"><i class="bi bi-receipt fs-2"></i><h5 class="mt-2"><?= $kpis['unpaid_invoices'] ?></h5><small>Fact. impayées</small></div></div></div>
    <div class="col-md-4 col-lg-2"><div class="card text-bg-secondary"><div class="card-body text-center"><i class="bi bi-cash-stack fs-2"></i><h5 class="mt-2"><?= formatMoney($kpis['revenue_month']) ?></h5><small>CA du mois</small></div></div></div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card"><div class="card-header"><h6 class="mb-0"><i class="bi bi-calendar-check"></i> Dernières réservations</h6></div>
            <div class="card-body p-0"><table class="table table-hover mb-0">
                <thead><tr><th>Client</th><th>Check-in</th><th>Check-out</th><th>Statut</th></tr></thead>
                <tbody>
                    <?php foreach ($recentReservations as $r): ?>
                    <tr>
                        <td><?= e($r['client_prenom'].' '.$r['client_nom']) ?></td>
                        <td><?= formatDate($r['date_checkin']) ?></td>
                        <td><?= formatDate($r['date_checkout']) ?></td>
                        <td><span class="badge bg-<?= $r['statut']==='confirmee'?'primary':($r['statut']==='en_cours'?'success':($r['statut']==='terminee'?'secondary':'danger')) ?>"><?= $r['statut'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card"><div class="card-header"><h6 class="mb-0"><i class="bi bi-calendar-heart"></i> Dernières demandes de congé</h6></div>
            <div class="card-body p-0"><table class="table table-hover mb-0">
                <thead><tr><th>Employé</th><th>Type</th><th>Du</th><th>Au</th><th>Statut</th></tr></thead>
                <tbody>
                    <?php foreach ($recentLeaves as $l): ?>
                    <tr>
                        <td><?= e($l['prenom'].' '.$l['nom']) ?></td>
                        <td><?= $l['type'] ?></td>
                        <td><?= formatDate($l['date_debut']) ?></td>
                        <td><?= formatDate($l['date_fin']) ?></td>
                        <td><span class="badge bg-<?= $l['statut']==='approuve'?'success':($l['statut']==='refuse'?'danger':'warning') ?>"><?= $l['statut'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>
