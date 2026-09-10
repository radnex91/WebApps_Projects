<div class="page-header">
    <div>
        <h1><?= htmlspecialchars($article->title) ?></h1>
        <div class="page-header-subtitle">
            <i class="bi bi-folder"></i> <?= htmlspecialchars($article->category_name ?? 'Général') ?>
            &middot; <i class="bi bi-person"></i> <?= htmlspecialchars($article->author_name ?? '?') ?>
            &middot; <i class="bi bi-calendar"></i> <?= $article->created_at ?>
        </div>
    </div>
    <div class="page-actions">
        <a href="/gestion-support/kb" class="btn-material btn-material-ghost">
            <i class="bi bi-arrow-left"></i> Retour
        </a>
    </div>
</div>

<div class="card-content">
    <div class="card-content-body">
        <div style="line-height:1.8"><?= $article->content ?></div>
    </div>
</div>

<?php if (\App\Helpers\Auth::role() === 'admin'): ?>
<div style="margin-top:16px;display:flex;gap:8px">
    <button class="btn-material btn-material-warning" onclick="openEditModal()">
        <i class="bi bi-pencil"></i> Modifier
    </button>
    <form method="POST" action="/gestion-support/kb/delete/<?= $article->id ?>" onsubmit="return confirm('Confirmer la suppression ?')">
        <button class="btn-material btn-material-danger"><i class="bi bi-trash"></i> Supprimer</button>
    </form>
</div>

<div class="modal-material-overlay" id="editArticleModal" style="display:none">
    <div class="modal-material">
        <div class="modal-material-header">
            <h5><i class="bi bi-pencil"></i> Modifier l'article</h5>
            <button class="modal-close" onclick="closeModal('editArticleModal')">&times;</button>
        </div>
        <form method="POST" action="/gestion-support/kb/edit/<?= $article->id ?>">
            <div class="modal-material-body">
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_title">Titre</label>
                    <input type="text" name="title" id="edit_title" class="form-input-material" value="<?= htmlspecialchars($article->title) ?>" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_category_id">Catégorie</label>
                    <select name="category_id" id="edit_category_id" class="form-input-material">
                        <option value="">Général</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat->id ?>" <?= $cat->id == $article->category_id ? 'selected' : '' ?>><?= htmlspecialchars($cat->nom) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_content">Contenu</label>
                    <textarea name="content" id="edit_content" class="form-input-material" rows="10" required><?= htmlspecialchars($article->content) ?></textarea>
                </div>
            </div>
            <div class="modal-material-footer">
                <button type="button" class="btn-material" onclick="closeModal('editArticleModal')">Annuler</button>
                <button type="submit" class="btn-material btn-material-primary"><i class="bi bi-check"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
