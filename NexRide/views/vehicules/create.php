<?php $title = 'Nouveau véhicule'; ?>
<div class="page-header">
    <h2>Nouveau véhicule</h2>
    <a href="<?= BASE_URL ?>/vehicules" class="btn btn-secondary">Retour</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/vehicules/store" class="form">
            <?= $csrf->field() ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="immatriculation">Immatriculation *</label>
                    <input type="text" name="immatriculation" id="immatriculation" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="marque">Marque *</label>
                    <input type="text" name="marque" id="marque" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="modele">Modèle *</label>
                    <input type="text" name="modele" id="modele" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="type_vehicule">Type</label>
                    <select name="type_vehicule" id="type_vehicule" class="form-control">
                        <option value="berline">Berline</option>
                        <option value="minibus">Minibus</option>
                        <option value="bus">Bus</option>
                        <option value="van">Van</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="capacite">Capacité *</label>
                    <input type="number" name="capacite" id="capacite" class="form-control" required min="1">
                </div>
                <div class="form-group">
                    <label for="annee">Année</label>
                    <input type="number" name="annee" id="annee" class="form-control" min="1990" max="<?= date('Y')+1 ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="couleur">Couleur</label>
                <input type="text" name="couleur" id="couleur" class="form-control">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="<?= BASE_URL ?>/vehicules" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>