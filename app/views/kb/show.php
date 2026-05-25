<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1><?= htmlspecialchars($article->title) ?></h1>
    <a href="/gestion-support/kb" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>
<div class="row">
    <div class="col-12">
        <p class="text-muted">Catégorie: <?= htmlspecialchars($article->category_name ?? 'Général') ?> | Par <?= htmlspecialchars($article->author_name ?? '?') ?> | <?= $article->created_at ?></p>
        <hr>
        <div><?= $article->content ?></div>
        <?php if (\App\Helpers\Auth::role() === 'admin'): ?>
        <hr>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editArticleModal"><i class="bi bi-pencil"></i> Modifier</button>
        <form method="POST" action="/gestion-support/kb/delete/<?= $article->id ?>" style="display:inline" onsubmit="return confirm('Confirmer?')">
            <button class="btn btn-danger"><i class="bi bi-trash"></i> Supprimer</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php if (\App\Helpers\Auth::role() === 'admin'): ?>
<div class="modal fade" id="editArticleModal"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5>Modifier l'article</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="/gestion-support/kb/edit/<?= $article->id ?>">
        <div class="modal-body">
            <div class="mb-3"><input type="text" name="title" class="form-control" value="<?= htmlspecialchars($article->title) ?>" required></div>
            <div class="mb-3"><textarea name="content" class="form-control" rows="10" required><?= htmlspecialchars($article->content) ?></textarea></div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary">Enregistrer</button></div>
    </form>
</div></div></div>
<?php endif; ?>
