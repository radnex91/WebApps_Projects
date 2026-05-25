<?php $title = 'Calendrier des réservations'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-calendar3"></i> Calendrier</h4>
    <div class="d-flex gap-2 align-items-center">
        <a href="<?= url('hotel/reservations/calendar?month=' . date('Y-m', strtotime($month . ' -1 month'))) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
        <span class="fw-bold"><?= date('F Y', strtotime($month)) ?></span>
        <a href="<?= url('hotel/reservations/calendar?month=' . date('Y-m', strtotime($month . ' +1 month'))) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
        <a href="<?= url('hotel/reservations/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nouvelle</a>
    </div>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table table-hover mb-0">
        <thead><tr><th>Client</th><th>Check-in</th><th>Check-out</th><th>Nuits</th><th>Statut</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($reservations as $r): ?>
            <tr>
                <td><?= e($r['client_prenom'].' '.$r['client_nom']) ?></td>
                <td><?= formatDate($r['date_checkin']) ?></td>
                <td><?= formatDate($r['date_checkout']) ?></td>
                <td><?= (strtotime($r['date_checkout']) - strtotime($r['date_checkin'])) / 86400 ?></td>
                <td><span class="badge bg-<?= $r['statut']==='confirmee'?'primary':'success' ?>"><?= $r['statut'] ?></span></td>
                <td><a href="<?= url('hotel/reservations/show/' . $r['id']) ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
