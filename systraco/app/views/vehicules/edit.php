<?php $title = 'Modifier Vehicule'; ob_start(); ?>
<div class="card">
    <div class="card-header"><h4><i class="fas fa-edit"></i> Modifier</h4></div>
    <div class="card-body">
        <form method="POST" action="<?php echo BASE_URL; ?>/vehicules/update/<?php echo $vehicule['id']; ?>">
            <div class="row">
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Immatriculation</label><input type="text" class="form-control" name="imatriculation" value="<?php echo $vehicule['imatriculation']; ?>" required></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Marque</label><input type="text" class="form-control" name="marque" value="<?php echo $vehicule['marque']; ?>"></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Modele</label><input type="text" class="form-control" name="modele" value="<?php echo $vehicule['modele']; ?>"></div></div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Mettre a jour</button>
            <a href="<?php echo BASE_URL; ?>/vehicules" class="btn btn-secondary">Retour</a>
        </form>
    </div>
</div>
<?php $content = ob_get_clean(); require_once ROOT_PATH . '/app/views/layout.php';