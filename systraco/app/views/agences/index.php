<?php
$title = 'Agences';
ob_start();
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-building"></i> Liste des Agences</h4>
        <a href="<?php echo BASE_URL; ?>/agences/add" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nouvelle Agence
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Nom</th>
                        <th>Ville</th>
                        <th>Cle</th>
                        <th>Telephone</th>
                        <th>Responsable</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agences as $agence): ?>
                    <tr>
                        <td><?php echo $agence['code_agence']; ?></td>
                        <td><?php echo $agence['nom_agence']; ?></td>
                        <td><?php echo $agence['ville_agence']; ?></td>
                        <td><?php echo $agence['cle_agence']; ?></td>
                        <td><?php echo $agence['telephone']; ?></td>
                        <td><?php echo $agence['responsable']; ?></td>
                        <td>
                            <span class="badge bg-<?php echo $agence['statut'] === 'Actif' ? 'success' : 'secondary'; ?>">
                                <?php echo $agence['statut']; ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/agences/edit/<?php echo $agence['id']; ?>" class="btn btn-sm btn-warning">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" action="<?php echo BASE_URL; ?>/agences/delete/<?php echo $agence['id']; ?>" class="d-inline">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Voulez-vous supprimer cette agence?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layout.php';