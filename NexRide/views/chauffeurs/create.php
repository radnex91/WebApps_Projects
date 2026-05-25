<?php $title = 'Nouveau chauffeur'; ?>
<div class="page-header">
    <h2>Nouveau chauffeur</h2>
    <a href="<?= BASE_URL ?>/chauffeurs" class="btn btn-secondary">Retour</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/chauffeurs/store" class="form">
            <?= $csrf->field() ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="prenom">Prénom *</label>
                    <input type="text" name="prenom" id="prenom" class="form-control" required>
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
            <div class="form-row">
                <div class="form-group">
                    <label for="permis">N° Permis *</label>
                    <input type="text" name="permis" id="permis" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="categorie_permis">Catégorie</label>
                    <input type="text" name="categorie_permis" id="categorie_permis" class="form-control" placeholder="Ex: B, C, D">
                </div>
            </div>
            <div class="form-group">
                <label for="adresse">Adresse</label>
                <textarea name="adresse" id="adresse" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="<?= BASE_URL ?>/chauffeurs" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>