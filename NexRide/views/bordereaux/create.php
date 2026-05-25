<?php $title = 'Nouveau bordereau'; ?>

<div class="page-header">
    <h2>Nouveau bordereau</h2>
    <a href="<?= BASE_URL ?>/bordereaux" class="btn btn-secondary">Retour</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/bordereaux/store" class="form" id="bordereauForm">
            <?= $csrf->field() ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="date_bordereau">Date *</label>
                    <input type="date" name="date_bordereau" id="date_bordereau" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label for="type">Type *</label>
                    <select name="type" id="type" class="form-control" required>
                        <option value="RECETTE">Recette</option>
                        <option value="DEPENSE">Dépense</option>
                        <option value="VIREMENT">Virement</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" class="form-control" rows="2"></textarea>
            </div>

            <h4 style="margin-top: 1.5rem;">Lignes du bordereau</h4>
            <div class="table-responsive">
                <table class="table" id="lignesTable">
                    <thead>
                        <tr><th>Libellé</th><th>Type</th><th>Catégorie</th><th>Montant</th><th>Billet lié</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="text" name="lignes[0][libelle]" class="form-control" required></td>
                            <td>
                                <select name="lignes[0][type]" class="form-control">
                                    <option value="RECETTE">Recette</option>
                                    <option value="DEPENSE">Dépense</option>
                                </select>
                            </td>
                            <td><input type="text" name="lignes[0][categorie]" class="form-control" placeholder="Ex: Transport"></td>
                            <td><input type="number" name="lignes[0][montant]" class="form-control" required min="0"></td>
                            <td>
                                <select name="lignes[0][billet_id]" class="form-control">
                                    <option value="">-</option>
                                    <?php foreach ($billetsJour as $b): ?>
                                        <option value="<?= $b->id ?>"><?= htmlspecialchars($b->reference) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()">X</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="ajouterLigne()">+ Ajouter une ligne</button>

            <div class="form-actions" style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">Créer le bordereau</button>
                <a href="<?= BASE_URL ?>/bordereaux" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<script>
let ligneIndex = 1;
function ajouterLigne() {
    const tbody = document.querySelector('#lignesTable tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" name="lignes[${ligneIndex}][libelle]" class="form-control" required></td>
        <td>
            <select name="lignes[${ligneIndex}][type]" class="form-control">
                <option value="RECETTE">Recette</option>
                <option value="DEPENSE">Dépense</option>
            </select>
        </td>
        <td><input type="text" name="lignes[${ligneIndex}][categorie]" class="form-control"></td>
        <td><input type="number" name="lignes[${ligneIndex}][montant]" class="form-control" required min="0"></td>
        <td>
            <select name="lignes[${ligneIndex}][billet_id]" class="form-control">
                <option value="">-</option>
                <?php foreach ($billetsJour as $b): ?>
                    <option value="<?= $b->id ?>"><?= htmlspecialchars($b->reference) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()">X</button></td>
    `;
    tbody.appendChild(tr);
    ligneIndex++;
}
</script>