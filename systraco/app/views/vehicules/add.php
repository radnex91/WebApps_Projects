<?php $title = 'Nouveau Vehicule'; ob_start(); ?>
<div class="card">
    <div class="card-header"><h4><i class="fas fa-plus"></i> Vehicule</h4></div>
    <div class="card-body">
        <form method="POST" action="<?php echo BASE_URL; ?>/vehicules/store">
            <div class="row">
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Immatriculation</label><input type="text" class="form-control" name="imatriculation" required></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Marque</label><input type="text" class="form-control" name="marque"></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Modele</label><input type="text" class="form-control" name="modele"></div></div>
            </div>
            <div class="row">
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Nb Places</label><input type="number" class="form-control" name="nb_place"></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Concessionnaire</label><input type="text" class="form-control" name="concessionnaire"></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Acquisition</label><input type="date" class="form-control" name="date_acquisition"></div></div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            <a href="<?php echo BASE_URL; ?>/vehicules" class="btn btn-secondary">Retour</a>
        </form>
    </div>
</div>
<?php $content = ob_get_clean(); require_once ROOT_PATH . '/app/views/layout.php';