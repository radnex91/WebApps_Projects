<div class="page-header">
    <div class="page-header-left">
        <h1><i class="bi bi-tags"></i> Catégories</h1>
    </div>
    <div class="page-header-right">
        <button class="btn-material btn-material-primary" onclick="openAddCategory()">
            <i class="bi bi-plus"></i> Nouveau
        </button>
    </div>
</div>

<div class="table-container" style="margin-top:20px">
    <table class="table-material">
        <thead>
            <tr><th>Nom</th><th style="width:110px">Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c->nom) ?></td>
                <td class="actions-cell">
                    <button class="btn-material btn-material-small btn-material-primary" title="Modifier" onclick="openEditCategory(<?= $c->id ?>, '<?= htmlspecialchars($c->nom, ENT_QUOTES) ?>')">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" action="<?= BASE_URL ?>/admin/categories/delete/<?= $c->id ?>" style="display:inline" onsubmit="return confirm('Confirmer la suppression ?')">
                        <button class="btn-material btn-material-small btn-material-danger" title="Supprimer">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add Modal -->
<div class="modal-material-overlay" id="addCategoryModal" style="display:none" onclick="if(event.target===this)closeAddCategory()">
    <div class="modal-material" onclick="event.stopPropagation()">
        <div class="modal-material-header">
            <h5><i class="bi bi-plus-circle"></i> Nouvelle catégorie</h5>
            <button class="modal-close" onclick="closeAddCategory()">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/categories/add" class="form-material">
            <div class="modal-material-body">
                <div class="form-group-material">
                    <label class="form-label-material" for="add_name">Nom de la catégorie</label>
                    <input type="text" name="name" id="add_name" class="form-input-material" placeholder="Ex: Réseau, Logiciel..." required>
                </div>
            </div>
            <div class="modal-material-footer">
                <button type="button" class="btn-material btn-material-danger" onclick="closeAddCategory()">Annuler</button>
                <button type="submit" class="btn-material btn-material-primary">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-material-overlay" id="editCategoryModal" style="display:none" onclick="if(event.target===this)closeEditCategory()">
    <div class="modal-material" onclick="event.stopPropagation()">
        <div class="modal-material-header">
            <h5><i class="bi bi-pencil"></i> Modifier la catégorie</h5>
            <button class="modal-close" onclick="closeEditCategory()">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/categories/edit/" id="editCategoryForm" class="form-material">
            <div class="modal-material-body">
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_name">Nom de la catégorie</label>
                    <input type="text" name="name" id="edit_name" class="form-input-material" required>
                </div>
            </div>
            <div class="modal-material-footer">
                <button type="button" class="btn-material btn-material-danger" onclick="closeEditCategory()">Annuler</button>
                <button type="submit" class="btn-material btn-material-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddCategory() {
    document.getElementById('add_name').value = '';
    document.getElementById('addCategoryModal').style.display = 'flex';
}

function closeAddCategory() {
    document.getElementById('addCategoryModal').style.display = 'none';
}

function openEditCategory(id, name) {
    document.getElementById('editCategoryForm').action = '<?= BASE_URL ?>/admin/categories/edit/' + id;
    document.getElementById('edit_name').value = name;
    document.getElementById('editCategoryModal').style.display = 'flex';
}

function closeEditCategory() {
    document.getElementById('editCategoryModal').style.display = 'none';
}
</script>
