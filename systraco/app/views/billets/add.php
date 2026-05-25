<?php
$title = 'Nouveau Billet';
ob_start();
?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<div class="card">
    <div class="card-header">
        <h4 class="mb-0"><i class="fas fa-ticket-alt"></i> Nouveau Billet</h4>
    </div>
    <div class="card-body">
        <form method="POST" action="<?php echo BASE_URL; ?>/billets/store">
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">N° Billet</label>
                        <input type="number" class="form-control" name="numero_ticket" value="<?php echo $nextNum; ?>" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="date_ticket" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Heure</label>
                        <input type="time" class="form-control" name="heure_ticket" value="<?php echo date('H:i'); ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Agence Depart</label>
                        <select class="form-select" name="agence_depart" id="agence_depart">
                            <option value="">Selectionner...</option>
                            <?php foreach ($agences_prefix as $ag): ?>
                            <option value="<?php echo $ag['nom_prefix']; ?>"><?php echo $ag['nom_prefix']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Agence Arrivee</label>
                        <select class="form-select" name="agence_arrivee" id="agence_arrivee" required>
                            <option value="">Selectionner...</option>
                            <?php foreach ($agences as $ag): ?>
                            <option value="<?php echo $ag['nom_agence']; ?>"><?php echo $ag['nom_agence']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Itineraire</label>
                        <select class="form-select" name="id_itineraire" id="itineraire" required>
                            <option value="">Selectionner...</option>
                            <?php foreach ($itineraires as $it): ?>
                            <option value="<?php echo $it['id']; ?>" data-tarif="<?php echo $it['tarif']; ?>"><?php echo $it['nom_itineraire']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Nom Passager</label>
                        <input type="text" class="form-control" name="nom_prenom_passager" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Telephone</label>
                        <input type="text" class="form-control" name="telephone_passager">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">N° CNI</label>
                        <input type="text" class="form-control" name="numero_cni_passager">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Classe</label>
                        <select class="form-select" name="classe_voyage">
                            <option value="Classique">Classique</option>
                            <option value="VIP">VIP</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type_passager">
                            <option value="Direct">Direct</option>
                            <option value="Transitaire">Transitaire</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Mode Paiement</label>
                        <select class="form-select" name="mode_paiement">
                            <option value="Especes">Especes</option>
                            <option value="Mobile Money">Mobile Money</option>
                            <option value="Carte">Carte</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Tarif</label>
                        <input type="number" class="form-control" name="tarif_voyage" id="tarif" value="0" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Somme Perçue</label>
                        <input type="number" class="form-control" name="somme_percue" id="somme_percue" value="0" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Reliquat</label>
                        <input type="number" class="form-control" id="reliquat" value="0" readonly>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Siege N°</label>
                        <input type="text" class="form-control" name="numero_siege">
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Observation</label>
                <textarea class="form-control" name="observation" rows="2"></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                <a href="<?php echo BASE_URL; ?>/billets" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
            </div>
        </form>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('#itineraire').change(function() {
        var tarif = $(this).find(':selected').data('tarif');
        $('#tarif').val(tarif || 0);
    });
    $('#somme_percue, #tarif').change(function() {
        var somme = parseInt($('#somme_percue').val()) || 0;
        var tarif = parseInt($('#tarif').val()) || 0;
        $('#reliquat').val(somme - tarif);
    });
});
</script>
<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layout.php';