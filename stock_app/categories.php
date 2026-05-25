<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

require_permission('manage_categories');

$editCategory = null;

if (isset($_GET['edit'])) {
    $editId = (int) ($_GET['edit'] ?? 0);
    if ($editId > 0) {
        $editStmt = $conn->prepare('SELECT id, category_name, description FROM categories WHERE id = ? LIMIT 1');
        $editStmt->bind_param('i', $editId);
        $editStmt->execute();
        $editCategory = $editStmt->get_result()->fetch_assoc();
        $editStmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $categoryName = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($categoryName === '') {
        set_flash('danger', 'Le nom de la categorie est obligatoire.');
        redirect('categories.php');
    }

    if ($categoryId > 0) {
        $stmt = $conn->prepare('UPDATE categories SET category_name = ?, description = ? WHERE id = ?');
        $stmt->bind_param('ssi', $categoryName, $description, $categoryId);
        $successMessage = 'Categorie mise a jour avec succes.';
    } else {
        $stmt = $conn->prepare('INSERT INTO categories (category_name, description) VALUES (?, ?)');
        $stmt->bind_param('ss', $categoryName, $description);
        $successMessage = 'Categorie ajoutee avec succes.';
    }

    $stmt->execute();
    $stmt->close();

    set_flash('success', $successMessage);
    redirect('categories.php');
}

$categories = $conn->query('SELECT id, category_name, description, created_at FROM categories ORDER BY category_name ASC');

require __DIR__ . '/partials/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card stat-card">
            <div class="card-header bg-white border-0 pt-4">
                <h1 class="h4 mb-0"><?= $editCategory ? 'Modifier la categorie' : 'Nouvelle categorie' ?></h1>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="category_id" value="<?= (int) ($editCategory['id'] ?? 0) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="category_name">Nom</label>
                        <input class="form-control" id="category_name" name="category_name" value="<?= e($editCategory['category_name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?= e($editCategory['description'] ?? '') ?></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit"><?= $editCategory ? 'Enregistrer' : 'Ajouter' ?></button>
                        <?php if ($editCategory): ?>
                            <a class="btn btn-outline-secondary" href="categories.php">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card stat-card">
            <div class="card-header bg-white border-0 pt-4">
                <h2 class="h4 mb-0">Liste des categories</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Creation</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($categories && $categories->num_rows > 0): ?>
                                <?php while ($category = $categories->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e($category['category_name']) ?></td>
                                        <td><?= e($category['description']) ?></td>
                                        <td><?= e($category['created_at']) ?></td>
                                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="categories.php?edit=<?= (int) $category['id'] ?>">Modifier</a></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted">Aucune categorie enregistree.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
