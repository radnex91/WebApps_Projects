<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1><i class="bi bi-pencil"></i> Modifier le ticket #<?= $ticket->id ?></h1>
</div>
<div class="row">
    <div class="col-md-8">
        <form method="POST" action="/gestion-support/tickets/<?= $ticket->id ?>/edit">
            <div class="mb-3"><label class="form-label">Titre</label><input type="text" name="title" class="form-control" value="<?= htmlspecialchars($ticket->titre) ?>" required></div>
            <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="5" required><?= htmlspecialchars($ticket->description) ?></textarea></div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Catégorie</label>
                    <select name="category_id" class="form-select">
                        <option value="">Sélectionner</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c->id ?>" <?= $c->id == $ticket->categorie_id ? 'selected' : '' ?>><?= htmlspecialchars($c->nom) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Priorité</label>
                    <select name="priority_id" class="form-select">
                        <option value="">Sélectionner</option>
                        <?php foreach ($priorities as $p): ?>
                        <option value="<?= $p->id ?>" <?= $p->id == $ticket->priorite_id ? 'selected' : '' ?>><?= htmlspecialchars($p->nom) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Enregistrer</button>
            <a href="/gestion-support/tickets/<?= $ticket->id ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div>
</div>
