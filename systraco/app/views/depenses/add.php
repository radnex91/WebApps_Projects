<?php $title = 'Nouvelle Depense'; ob_start(); ?>
<div class="card">
    <div class="card-header"><h4><i class="fas fa-plus"></i> Depense</h4></div>
    <div class="card-body">
        <form method="POST" action="<?php echo BASE_URL; ?>/depenses/store">
            <div class="row">
                <div class="col-md-3"><div class="mb-3"><label class="form-label">Agence</label><select class="form-select" name="nom_agence"><option value="">Selectionner...</option><?php foreach ($agences as $ag): ?><option value="<?php echo $ag['nom_agence']; ?>"><?php echo $ag['nom_agence']; ?></option><?php endforeach; ?></select></div></div>
                <div class="col-md-3"><div class="mb-3"><label class="form-label">Type</label><select class="form-select" name="type_operation"><option value="Carburant">Carburant</option><option value="Peage">Peage</option><option value="Repas">Repas</option><option value="Divers">Divers</option></select></div></div>
                <div class="col-md-3"><div class="mb-3"><label class="form-label">Montant</label><input type="number" class="form-control" name="montant_depenses" required></div></div>
                <div class="col-md-3"><div class="mb-3"><label class="form-label">Date</label><input type="date" class="form-control" name="date_depense" value="<?php echo date('Y-m-d'); ?>"></div></div>
            </div>
            <div class="mb-3"><label class="form-label">Libelle</label><textarea class="form-control" name="libelle" rows="2"></textarea></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            <a href="<?php echo BASE_URL; ?>/depenses" class="btn btn-secondary">Retour</a>
        </form>
    </div>
</div>
<?php $content = ob_get_clean(); require_once ROOT_PATH . '/app/views/layout.php';