<div class="page-header">
    <div>
        <h1><i class="bi bi-plus-circle"></i> Nouveau ticket</h1>
    </div>
</div>

<div style="max-width:700px;margin-top:20px">
    <div class="card-material">
        <div class="card-material-body">
            <form method="POST" action="/gestion-support/tickets/create">
                <div class="form-group-material">
                    <label class="form-label-material" for="title">Titre</label>
                    <input type="text" name="title" id="title" class="form-input-material" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="description">Description</label>
                    <textarea name="description" id="description" class="form-input-material" rows="5" required></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div class="form-group-material">
                        <label class="form-label-material" for="category_id">Catégorie</label>
                        <select name="category_id" id="category_id" class="form-input-material">
                            <option value="">Sélectionner</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= $c->id ?>"><?= htmlspecialchars($c->nom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-material">
                        <label class="form-label-material" for="priority_id">Priorité</label>
                        <select name="priority_id" id="priority_id" class="form-input-material">
                            <option value="">Sélectionner</option>
                            <?php foreach ($priorities as $p): ?>
                            <option value="<?= $p->id ?>"><?= htmlspecialchars($p->nom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display:flex;gap:8px;margin-top:20px">
                    <button type="submit" class="btn-material btn-material-primary"><i class="bi bi-send"></i> Créer</button>
                    <a href="/gestion-support/tickets" class="btn-material"><i class="bi bi-x"></i> Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>
