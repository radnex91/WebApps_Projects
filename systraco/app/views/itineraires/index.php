<?php $title = 'Itineraires'; ob_start(); ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-route"></i> Itineraires</h4>
        <a href="<?php echo BASE_URL; ?>/itineraires/add" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau</a>
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead><tr><th>Nom</th><th>Depart</th><th>Arrivee</th><th>Tarif</th><th>Classe</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($itineraires as $i): ?>
                <tr>
                    <td><?php echo $i['nom_itineraire']; ?></td>
                    <td><?php echo $i['agence_depart']; ?></td>
                    <td><?php echo $i['agence_arrivee']; ?></td>
                    <td><?php echo number_format($i['tarif'], 0, ',', ' '); ?></td>
                    <td><?php echo $i['classe_itineraire']; ?></td>
                    <td><span class="badge bg-<?php echo $i['statut'] === 'Actif' ? 'success' : 'secondary'; ?>"><?php echo $i['statut']; ?></span></td>
                    <td>
                        <a href="<?php echo BASE_URL; ?>/itineraires/edit/<?php echo $i['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                        <form method="POST" action="<?php echo BASE_URL; ?>/itineraires/delete/<?php echo $i['id']; ?>" class="d-inline"><button class="btn btn-sm btn-danger" onclick="return confirm('Supprimer?')"><i class="fas fa-trash"></i></button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $content = ob_get_clean(); require_once ROOT_PATH . '/app/views/layout.php';