<div class="page-header">
    <div>
        <h1><i class="bi bi-pencil"></i> Modifier le ticket #<?= $ticket->id ?></h1>
    </div>
</div>

<div style="max-width:700px;margin-top:20px">
    <div class="card-material">
        <div class="card-material-body">
            <form method="POST" action="/gestion-support/tickets/<?= $ticket->id ?>/edit">
                <div class="form-group-material">
                    <label class="form-label-material" for="title">Titre</label>
                    <input type="text" name="title" id="title" class="form-input-material" value="<?= htmlspecialchars($ticket->titre) ?>" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="description">Description</label>
                    <textarea name="description" id="description" class="form-input-material" rows="5" required><?= htmlspecialchars($ticket->description) ?></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div class="form-group-material">
                        <label class="form-label-material" for="category_id">Catégorie</label>
                        <select name="category_id" id="category_id" class="form-input-material">
                            <option value="">Sélectionner</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= $c->id ?>" <?= $c->id == $ticket->categorie_id ? 'selected' : '' ?>><?= htmlspecialchars($c->nom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-material">
                        <label class="form-label-material" for="priority_id">Priorité</label>
                        <select name="priority_id" id="priority_id" class="form-input-material">
                            <option value="">Sélectionner</option>
                            <?php foreach ($priorities as $p): ?>
                            <option value="<?= $p->id ?>" <?= $p->id == $ticket->priorite_id ? 'selected' : '' ?>><?= htmlspecialchars($p->nom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display:flex;gap:8px;margin-top:20px">
                    <button type="submit" class="btn-material btn-material-primary"><i class="bi bi-save"></i> Enregistrer</button>
                    <a href="/gestion-support/tickets/<?= $ticket->id ?>" class="btn-material"><i class="bi bi-x"></i> Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>
