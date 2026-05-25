<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1><i class="bi bi-plus-circle"></i> Nouveau ticket</h1>
</div>
<div class="row">
    <div class="col-md-8">
        <form method="POST" action="/gestion-support/tickets/create">
            <div class="mb-3"><label class="form-label">Titre</label><input type="text" name="title" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="5" required></textarea></div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Catégorie</label>
                    <select name="category_id" class="form-select">
                        <option value="">Sélectionner</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c->id ?>"><?= htmlspecialchars($c->nom) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Priorité</label>
                    <select name="priority_id" class="form-select">
                        <option value="">Sélectionner</option>
                        <?php foreach ($priorities as $p): ?>
                        <option value="<?= $p->id ?>"><?= htmlspecialchars($p->nom) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Créer</button>
            <a href="/gestion-support/tickets" class="btn btn-secondary">Annuler</a>
        </form>
    </div>
</div>
