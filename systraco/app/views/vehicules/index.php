<?php $title = 'Vehicules'; ob_start(); ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-bus"></i> Vehicules</h4>
        <a href="<?php echo BASE_URL; ?>/vehicules/add" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau</a>
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead><tr><th>Immatriculation</th><th>Marque</th><th>Modele</th><th>Places</th><th>Groupe</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($vehicules as $v): ?>
                <tr>
                    <td><?php echo $v['imatriculation']; ?></td>
                    <td><?php echo $v['marque']; ?></td>
                    <td><?php echo $v['modele']; ?></td>
                    <td><?php echo $v['nb_place']; ?></td>
                    <td><?php echo $v['nom_groupe']; ?></td>
                    <td><span class="badge bg-<?php echo $v['statut'] === 'Actif' ? 'success' : 'secondary'; ?>"><?php echo $v['statut']; ?></span></td>
                    <td>
                        <a href="<?php echo BASE_URL; ?>/vehicules/edit/<?php echo $v['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                        <form method="POST" action="<?php echo BASE_URL; ?>/vehicules/delete/<?php echo $v['id']; ?>" class="d-inline"><button class="btn btn-sm btn-danger" onclick="return confirm('Supprimer?')"><i class="fas fa-trash"></i></button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $content = ob_get_clean(); require_once ROOT_PATH . '/app/views/layout.php';