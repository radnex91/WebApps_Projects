<div class="page-header">
    <div class="page-header-left">
        <h1><i class="bi bi-book"></i> Base de connaissances</h1>
    </div>
    <div>
        <button class="btn-material btn-material-primary" onclick="openModal('addArticleModal')">
            <i class="bi bi-plus-circle"></i> Article
        </button>
        <button class="btn-material btn-material-secondary" onclick="openModal('addCategoryModal')">
            <i class="bi bi-tag"></i> Catégorie
        </button>
    </div>
</div>

<div class="row" style="margin-top:20px">
    <div class="col-md-3">
        <div class="card-material" style="padding:0">
            <div class="kb-categories">
                <a href="<?= BASE_URL ?>/kb" class="kb-category-link active">Tous les articles</a>
                <?php foreach ($categories as $cat): ?>
                <a href="<?= BASE_URL ?>/kb?category=<?= $cat->id ?>" class="kb-category-link">
                    <i class="bi bi-folder"></i> <?= htmlspecialchars($cat->nom) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        <?php if (empty($articles)): ?>
        <div class="empty-state">
            <i class="bi bi-book" style="font-size:48px;color:#9e9e9e"></i>
            <p>Aucun article trouvé</p>
        </div>
        <?php else: ?>
            <?php foreach ($articles as $a): ?>
            <div class="card-material">
                <div class="card-material-body">
                    <div style="display:flex;justify-content:space-between;align-items:start">
                        <div>
                            <h5 style="margin:0">
                                <a href="<?= BASE_URL ?>/kb/<?= $a->id ?>" class="ticket-link"><?= htmlspecialchars($a->title) ?></a>
                            </h5>
                            <p class="text-secondary" style="font-size:13px;margin-top:4px">
                                <i class="bi bi-folder"></i> <?= htmlspecialchars($a->category_name ?? 'Général') ?>
                                &middot; <i class="bi bi-person"></i> <?= htmlspecialchars($a->author_name ?? '?') ?>
                                &middot; <i class="bi bi-calendar"></i> <?= $a->created_at ?>
                            </p>
                        </div>
                        <?php if (\App\Helpers\Auth::role() === 'admin'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/kb/delete/<?= $a->id ?>" onsubmit="return confirm('Confirmer la suppression ?')">
                            <button class="btn-material btn-material-small btn-material-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <p class="text-secondary" style="margin-top:8px">
                        <?= substr(htmlspecialchars(strip_tags($a->content)), 0, 200) ?>...
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Article Modal -->
<div class="modal-material-overlay" id="addArticleModal" style="display:none">
    <div class="modal-material">
        <div class="modal-material-header">
            <h5><i class="bi bi-plus-circle"></i> Ajouter un article</h5>
            <button class="modal-close" onclick="closeModal('addArticleModal')">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/kb/add">
            <div class="modal-material-body">
                <div class="form-group-material">
                    <label class="form-label-material" for="title">Titre</label>
                    <input type="text" name="title" id="title" class="form-input-material" placeholder="Titre de l'article" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="category_id">Catégorie</label>
                    <select name="category_id" id="category_id" class="form-input-material">
                        <option value="">Général</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat->id ?>"><?= htmlspecialchars($cat->nom) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="content">Contenu</label>
                    <textarea name="content" id="content" class="form-input-material" rows="10" placeholder="Contenu (HTML supporté)" required></textarea>
                </div>
            </div>
            <div class="modal-material-footer">
                <button type="button" class="btn-material" onclick="closeModal('addArticleModal')">Annuler</button>
                <button type="submit" class="btn-material btn-material-primary"><i class="bi bi-check"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal-material-overlay" id="addCategoryModal" style="display:none">
    <div class="modal-material">
        <div class="modal-material-header">
            <h5><i class="bi bi-tag"></i> Ajouter une catégorie</h5>
            <button class="modal-close" onclick="closeModal('addCategoryModal')">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/kb/category/add">
            <div class="modal-material-body">
                <div class="form-group-material">
                    <label class="form-label-material" for="cat_name">Nom</label>
                    <input type="text" name="name" id="cat_name" class="form-input-material" placeholder="Nom de la catégorie" required>
                </div>
            </div>
            <div class="modal-material-footer">
                <button type="button" class="btn-material" onclick="closeModal('addCategoryModal')">Annuler</button>
                <button type="submit" class="btn-material btn-material-primary"><i class="bi bi-check"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
document.querySelectorAll('.modal-material-overlay').forEach(el => {
    el.addEventListener('click', function(e) { if (e.target === this) this.style.display = 'none'; });
});
</script>
