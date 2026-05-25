<?php $title = 'Reservations'; ob_start(); ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-calendar-check"></i> Reservations</h4>
        <a href="<?php echo BASE_URL; ?>/reservations/add" class="btn btn-primary"><i class="fas fa-plus"></i> Nouvelle</a>
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead><tr><th>N°</th><th>Date</th><th>Passager</th><th>Telephone</th><th>Itineraire</th><th>Tarif</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($reservations as $r): ?>
                <tr>
                    <td><?php echo $r['numero_reservation']; ?></td>
                    <td><?php echo $r['date_reservation']; ?></td>
                    <td><?php echo $r['nom_prenom_passager']; ?></td>
                    <td><?php echo $r['telephone_passager']; ?></td>
                    <td><?php echo $r['itineraire']; ?></td>
                    <td><?php echo number_format($r['tarif'], 0, ',', ' '); ?></td>
                    <td><span class="badge bg-<?php echo $r['statut'] === 'Confirme' ? 'success' : 'warning'; ?>"><?php echo $r['statut']; ?></span></td>
                    <td>
                        <?php if ($r['statut'] === 'Reserve'): ?>
                        <form method="POST" action="<?php echo BASE_URL; ?>/reservations/confirmer/<?php echo $r['id']; ?>" class="d-inline"><button class="btn btn-sm btn-success"><i class="fas fa-check"></i></button></form>
                        <?php endif; ?>
                        <form method="POST" action="<?php echo BASE_URL; ?>/reservations/delete/<?php echo $r['id']; ?>" class="d-inline"><button class="btn btn-sm btn-danger" onclick="return confirm('Supprimer?')"><i class="fas fa-trash"></i></button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $content = ob_get_clean(); require_once ROOT_PATH . '/app/views/layout.php';