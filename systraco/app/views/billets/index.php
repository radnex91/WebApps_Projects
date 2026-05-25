<?php
$title = 'Liste Billets';
ob_start();
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-ticket-alt"></i> Billets Vendus</h4>
        <a href="<?php echo BASE_URL; ?>/billets/add" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nouveau Billet
        </a>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">Date debut</label>
                <input type="date" class="form-control" name="date_debut" value="<?php echo $date_debut; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date fin</label>
                <input type="date" class="form-control" name="date_fin" value="<?php echo $date_fin; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary d-block"><i class="fas fa-search"></i> Rechercher</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Numero</th>
                        <th>Date</th>
                        <th>Passager</th>
                        <th>Trajet</th>
                        <th>Tarif</th>
                        <th>Perçu</th>
                        <th>Classe</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($billets as $billet): ?>
                    <tr>
                        <td><?php echo $billet['numero_ticket']; ?></td>
                        <td><?php echo $billet['date_ticket']; ?></td>
                        <td><?php echo $billet['nom_prenom_passager']; ?></td>
                        <td><?php echo $billet['agence_depart'] . ' - ' . $billet['agence_arrivee']; ?></td>
                        <td><?php echo number_format($billet['tarif_voyage_decide'], 0, ',', ' '); ?></td>
                        <td><?php echo number_format($billet['somme_percue'], 0, ',', ' '); ?></td>
                        <td><?php echo $billet['classe_voyage']; ?></td>
                        <td><span class="badge bg-<?php echo $billet['statut_ticket'] === 'Vendu' ? 'success' : 'warning'; ?>"><?php echo $billet['statut_ticket']; ?></span></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/billets/edit/<?php echo $billet['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
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