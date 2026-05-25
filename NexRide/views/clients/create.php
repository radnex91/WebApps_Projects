<?php $title = 'Nouveau client'; ?>
<div class="page-header">
    <h2>Nouveau client</h2>
    <a href="<?= BASE_URL ?>/clients" class="btn btn-secondary">Retour</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/clients/store" class="form">
            <?= $csrf->field() ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="prenom">Prénom</label>
                    <input type="text" name="prenom" id="prenom" class="form-control">
                </div>
                <div class="form-group">
                    <label for="nom">Nom *</label>
                    <input type="text" name="nom" id="nom" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="telephone">Téléphone *</label>
                    <input type="tel" name="telephone" id="telephone" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label for="adresse">Adresse</label>
                <textarea name="adresse" id="adresse" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="piece_identite">Pièce d'identité</label>
                    <select name="piece_identite" id="piece_identite" class="form-control">
                        <option value="">-</option>
                        <option value="CNI">CNI</option>
                        <option value="PASSEPORT">Passeport</option>
                        <option value="PERMIS">Permis</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="num_piece">Numéro pièce</label>
                    <input type="text" name="num_piece" id="num_piece" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea name="notes" id="notes" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="<?= BASE_URL ?>/clients" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>