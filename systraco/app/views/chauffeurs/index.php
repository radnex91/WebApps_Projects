<?php
$title = 'Chauffeurs';
ob_start();
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-user-tie"></i> Chauffeurs</h4>
        <a href="<?php echo BASE_URL; ?>/chauffeurs/add" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau</a>
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead><tr><th>Nom</th><th>Telephone</th><th>CNI</th><th>Permis</th><th>Expiration</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($chauffeurs as $c): ?>
                <tr>
                    <td><?php echo $c['nom_prenom_chauffeur']; ?></td>
                    <td><?php echo $c['telephone']; ?></td>
                    <td><?php echo $c['numero_cni']; ?></td>
                    <td><?php echo $c['numero_permis']; ?></td>
                    <td><?php echo $c['date_experiration']; ?></td>
                    <td><span class="badge bg-<?php echo $c['statut'] === 'Actif' ? 'success' : 'secondary'; ?>"><?php echo $c['statut']; ?></span></td>
                    <td>
                        <a href="<?php echo BASE_URL; ?>/chauffeurs/edit/<?php echo $c['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                        <form method="POST" action="<?php echo BASE_URL; ?>/chauffeurs/delete/<?php echo $c['id']; ?>" class="d-inline"><button class="btn btn-sm btn-danger" onclick="return confirm('Supprimer?')"><i class="fas fa-trash"></i></button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $content = ob_get_clean(); require_once ROOT_PATH . '/app/views/layout.php';