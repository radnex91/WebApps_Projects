<?php $title = 'Nouveau voyage'; ?>

<div class="page-header">
    <h2>Nouveau voyage</h2>
    <a href="<?= BASE_URL ?>/voyages" class="btn btn-secondary">Retour</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/voyages/store" class="form">
            <?= $csrf->field() ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="titre">Titre *</label>
                    <input type="text" name="titre" id="titre" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="type_voyage">Type *</label>
                    <select name="type_voyage" id="type_voyage" class="form-control" required>
                        <?php foreach (unserialize(TYPE_VOYAGE) as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="depart">Départ *</label>
                    <input type="text" name="depart" id="depart" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="destination">Destination *</label>
                    <input type="text" name="destination" id="destination" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="date_depart">Date de départ *</label>
                    <input type="datetime-local" name="date_depart" id="date_depart" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="date_arrivee">Date d'arrivée</label>
                    <input type="datetime-local" name="date_arrivee" id="date_arrivee" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="vehicule_id">Véhicule</label>
                    <select name="vehicule_id" id="vehicule_id" class="form-control">
                        <option value="">Sélectionner</option>
                        <?php foreach ($vehicules as $veh): ?>
                            <option value="<?= $veh->id ?>"><?= htmlspecialchars($veh->immatriculation) ?> - <?= htmlspecialchars($veh->marque) ?> (<?= $veh->capacite ?> places)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="chauffeur_id">Chauffeur</label>
                    <select name="chauffeur_id" id="chauffeur_id" class="form-control">
                        <option value="">Sélectionner</option>
                        <?php foreach ($chauffeurs as $ch): ?>
                            <option value="<?= $ch->id ?>"><?= htmlspecialchars($ch->getNomComplet()) ?> - <?= htmlspecialchars($ch->permis) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="tarif_base">Tarif base (FCFA) *</label>
                    <input type="number" name="tarif_base" id="tarif_base" class="form-control" required min="0">
                </div>
                <div class="form-group">
                    <label for="nombre_places">Nombre de places</label>
                    <input type="number" name="nombre_places" id="nombre_places" class="form-control" min="1" placeholder="Auto si véhicule sélectionné">
                </div>
            </div>
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea name="notes" id="notes" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Créer le voyage</button>
                <a href="<?= BASE_URL ?>/voyages" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>