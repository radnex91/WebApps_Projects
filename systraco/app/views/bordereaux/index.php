<?php $title = 'Bordereaux'; ob_start(); ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-file-alt"></i> Bordereaux</h4>
        <a href="<?php echo BASE_URL; ?>/bordereaux/add" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau</a>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input type="date" class="form-control" name="date" value="<?php echo $date; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary d-block">Rechercher</button>
            </div>
        </form>
        <table class="table table-hover">
            <thead><tr><th>N°</th><th>Date</th><th>Destination</th><th>Vehicule</th><th>Chauffeur</th><th>Places</th><th>Recette</th></tr></thead>
            <tbody>
                <?php foreach ($bordereaux as $b): ?>
                <tr>
                    <td><?php echo $b['numero']; ?></td>
                    <td><?php echo $b['date_bordereau']; ?></td>
                    <td><?php echo $b['destination']; ?></td>
                    <td><?php echo $b['imatriculation']; ?></td>
                    <td><?php echo $b['nom_prenom_chauffeur']; ?></td>
                    <td><?php echo $b['nombre_place']; ?></td>
                    <td><?php echo number_format($b['recette_caisse'], 0, ',', ' '); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $content = ob_get_clean(); require_once ROOT_PATH . '/app/views/layout.php';