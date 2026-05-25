<?php $title = 'Nouvelle Reservation'; ob_start(); ?>
<div class="card">
    <div class="card-header"><h4><i class="fas fa-plus"></i> Reservation</h4></div>
    <div class="card-body">
        <form method="POST" action="<?php echo BASE_URL; ?>/reservations/store">
            <div class="row">
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Date</label><input type="date" class="form-control" name="date_reservation" value="<?php echo date('Y-m-d'); ?>" required></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Heure</label><input type="time" class="form-control" name="heure_reservation" value="<?php echo date('H:i'); ?>"></div></div>
            </div>
            <div class="row">
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Nom Passager</label><input type="text" class="form-control" name="nom_prenom_passager" required></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Telephone</label><input type="text" class="form-control" name="telephone_passager"></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">N° CNI</label><input type="text" class="form-control" name="numero_cni_passager"></div></div>
            </div>
            <div class="row">
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Itineraire</label><select class="form-select" name="id_itineraire"><option value="">Selectionner...</option><?php foreach ($itineraires as $i): ?><option value="<?php echo $i['id']; ?>"><?php echo $i['nom_itineraire']; ?></option><?php endforeach; ?></select></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Classe</label><select class="form-select" name="classe_voyage"><option value="Classique">Classique</option><option value="VIP">VIP</option></select></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Tarif</label><input type="number" class="form-control" name="tarif" value="0"></div></div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            <a href="<?php echo BASE_URL; ?>/reservations" class="btn btn-secondary">Retour</a>
        </form>
    </div>
</div>
<?php $content = ob_get_clean(); require_once ROOT_PATH . '/app/views/layout.php';