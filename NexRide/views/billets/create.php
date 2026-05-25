<?php $title = 'Nouveau billet'; ?>

<div class="page-header">
    <h2>Nouveau billet</h2>
    <a href="<?= BASE_URL ?>/billets" class="btn btn-secondary">Retour</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/billets/store" class="form">
            <?= $csrf->field() ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="voyage_id">Voyage *</label>
                    <select name="voyage_id" id="voyage_id" class="form-control" required>
                        <option value="">Sélectionner un voyage</option>
                        <?php foreach ($voyages as $v): ?>
                        <option value="<?= $v->id ?>">
                            <?= htmlspecialchars($v->reference) ?> - <?= htmlspecialchars($v->depart) ?> &rarr; <?= htmlspecialchars($v->destination) ?> (<?= date('d/m/Y H:i', strtotime($v->date_depart)) ?>) - <?= $v->places_disponibles ?> places
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="siege">Siège</label>
                    <input type="text" name="siege" id="siege" class="form-control" placeholder="Ex: A1">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="nom_passager">Nom du passager *</label>
                    <input type="text" name="nom_passager" id="nom_passager" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="telephone_passager">Téléphone *</label>
                    <input type="tel" name="telephone_passager" id="telephone_passager" class="form-control" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email_passager">Email</label>
                    <input type="email" name="email_passager" id="email_passager" class="form-control">
                </div>
                <div class="form-group">
                    <label for="telephone_client">Téléphone client (si existant)</label>
                    <input type="tel" name="telephone_client" id="telephone_client" class="form-control" placeholder="Recherche automatique">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="montant">Montant (FCFA) *</label>
                    <input type="number" name="montant" id="montant" class="form-control" required min="0">
                </div>
                <div class="form-group">
                    <label for="remise">Remise (FCFA)</label>
                    <input type="number" name="remise" id="remise" class="form-control" min="0" value="0">
                </div>
                <div class="form-group">
                    <label for="mode_paiement">Mode de paiement *</label>
                    <select name="mode_paiement" id="mode_paiement" class="form-control" required>
                        <option value="ESPECES">Espèces</option>
                        <option value="CARTE">Carte</option>
                        <option value="MOBILE_MONEY">Mobile Money</option>
                        <option value="VIREMENT">Virement</option>
                        <option value="AUTRE">Autre</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea name="notes" id="notes" class="form-control" rows="2"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Créer le billet</button>
                <a href="<?= BASE_URL ?>/billets" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('telephone_client').addEventListener('blur', function() {
    if (this.value.length >= 4) {
        fetch('<?= BASE_URL ?>/api/search-clients?q=' + encodeURIComponent(this.value))
            .then(r => r.json())
            .then(clients => {
                if (clients.length === 1) {
                    document.getElementById('nom_passager').value = clients[0].nom;
                }
            });
    }
});
</script>