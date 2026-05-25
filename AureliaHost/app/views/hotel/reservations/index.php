<?php $title = 'Réservations'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-calendar-check"></i> Réservations</h4>
    <div class="d-flex gap-2">
        <a href="<?= url('hotel/reservations/calendar') ?>" class="btn btn-outline-info"><i class="bi bi-calendar3"></i> Calendrier</a>
        <button type="button" class="btn btn-primary" onclick="openCreateModal()"><i class="bi bi-plus-lg"></i> Nouvelle réservation</button>
    </div>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table table-hover mb-0">
        <thead><tr><th>Client</th><th>Check-in</th><th>Check-out</th><th>Total</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($reservations as $r): ?>
            <tr>
                <td><?= e($r['client_prenom'].' '.$r['client_nom']) ?></td>
                <td><?= formatDate($r['date_checkin']) ?></td>
                <td><?= formatDate($r['date_checkout']) ?></td>
                <td><?= formatMoney($r['montant_total']) ?></td>
                <td><span class="badge bg-<?= $r['statut']==='confirmee'?'primary':($r['statut']==='en_cours'?'success':($r['statut']==='terminee'?'secondary':'danger')) ?>"><?= $r['statut'] ?></span></td>
                <td>
                    <a href="<?= url('hotel/reservations/show/' . $r['id']) ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></a>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?= $r['id'] ?>)"><i class="bi bi-pencil"></i></button>
                    <a href="<?= url('hotel/reservations/delete/' . $r['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer cette réservation ?')"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div></div>

<?php require_once __DIR__ . '/_modal.php'; ?>
