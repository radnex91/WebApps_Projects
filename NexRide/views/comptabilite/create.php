<?php $title = 'Nouvelle écriture comptable'; ?>

<div class="page-header">
    <h2>Nouvelle écriture comptable</h2>
    <a href="<?= BASE_URL ?>/comptabilite" class="btn btn-secondary">Retour</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/comptabilite/store" class="form">
            <?= $csrf->field() ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="date_ecriture">Date *</label>
                    <input type="date" name="date_ecriture" id="date_ecriture" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label for="type_operation">Type *</label>
                    <select name="type_operation" id="type_operation" class="form-control" required>
                        <option value="VENTE">Vente</option>
                        <option value="ACHAT">Achat</option>
                        <option value="SALAIRE">Salaire</option>
                        <option value="CARBURANT">Carburant</option>
                        <option value="MAINTENANCE">Maintenance</option>
                        <option value="ASSURANCE">Assurance</option>
                        <option value="TAXE">Taxe</option>
                        <option value="AUTRE">Autre</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="libelle">Libellé *</label>
                <input type="text" name="libelle" id="libelle" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="compte">Compte comptable *</label>
                    <input type="text" name="compte" id="compte" class="form-control" placeholder="Ex: 701000" required>
                </div>
                <div class="form-group">
                    <label for="compte_label">Libellé du compte</label>
                    <input type="text" name="compte_label" id="compte_label" class="form-control" placeholder="Ex: Ventes de billets">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="debit">Débit (FCFA)</label>
                    <input type="number" name="debit" id="debit" class="form-control" min="0" value="0">
                </div>
                <div class="form-group">
                    <label for="credit">Crédit (FCFA)</label>
                    <input type="number" name="credit" id="credit" class="form-control" min="0" value="0">
                </div>
                <div class="form-group">
                    <label for="caisse_id">Caisse associée</label>
                    <select name="caisse_id" id="caisse_id" class="form-control">
                        <option value="">-</option>
                        <?php foreach ($caisses as $c): ?>
                            <option value="<?= $c->id ?>"><?= htmlspecialchars($c->libelle) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea name="notes" id="notes" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="<?= BASE_URL ?>/comptabilite" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>