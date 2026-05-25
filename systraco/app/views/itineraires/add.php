<?php $title = 'Nouveau Itineraire'; ob_start(); ?>
<div class="card">
    <div class="card-header"><h4><i class="fas fa-plus"></i> Itineraire</h4></div>
    <div class="card-body">
        <form method="POST" action="<?php echo BASE_URL; ?>/itineraires/store">
            <div class="row">
                <div class="col-md-6"><div class="mb-3"><label class="form-label">Nom Itineraire</label><input type="text" class="form-control" name="nom_itineraire" required></div></div>
                <div class="col-md-3"><div class="mb-3"><label class="form-label">Classe</label><select class="form-select" name="classe_itineraire"><option value="Classique">Classique</option><option value="VIP">VIP</option></select></div></div>
                <div class="col-md-3"><div class="mb-3"><label class="form-label">Tarif</label><input type="number" class="form-control" name="tarif" value="0"></div></div>
            </div>
            <div class="row">
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Agence Depart</label><input type="text" class="form-control" name="agence_depart"></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Arrivee</label><input type="text" class="form-control" name="agence_arrivee"></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Terminal</label><input type="text" class="form-control" name="terminal"></div></div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            <a href="<?php echo BASE_URL; ?>/itineraires" class="btn btn-secondary">Retour</a>
        </form>
    </div>
</div>
<?php $content = ob_get_clean(); require_once ROOT_PATH . '/app/views/layout.php';