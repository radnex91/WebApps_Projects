<?php
// ButcheryPOS - Categories Page
$categories = $productService->getAllCategories();

$editCategory = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    foreach ($categories as $cat) {
        if ((int)$cat['id'] === (int)$_GET['id']) {
            $editCategory = $cat;
            break;
        }
    }
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><?= t('categories') ?></h4>
        <small class="text-muted"><?= count($categories) ?> <?= t('categories_found') ?></small>
    </div>
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="clearCategoryForm()">
        <i class="bi bi-plus-lg"></i> <?= t('add_category') ?>
    </button>
</div>

<!-- Add/Edit Category Form (Inline Card) -->
<div class="card border-0 shadow-sm mb-4" id="categoryFormCard" style="display: <?= $editCategory ? 'block' : 'none' ?>;">
    <div class="card-body">
        <form id="categoryForm" method="POST" action="<?= url('?action=save_category') ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="category_id" id="categoryId" value="<?= $editCategory ? (int)$editCategory['id'] : '' ?>">

            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label"><?= t('category_name') ?> <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="categoryName" class="form-control" required
                           value="<?= $editCategory ? e($editCategory['name']) : '' ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= t('description') ?></label>
                    <input type="text" name="description" id="categoryDescription" class="form-control"
                           value="<?= $editCategory ? e($editCategory['description'] ?? '') : '' ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= t('sort_order') ?></label>
                    <input type="number" name="sort_order" id="categorySortOrder" class="form-control" min="0"
                           value="<?= $editCategory ? (int)($editCategory['sort_order'] ?? 0) : 0 ?>">
                </div>
                <div class="col-md-2">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> <?= t('save') ?>
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="hideCategoryForm()">
                            <?= t('cancel') ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Categories Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($categories)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-tags fs-1"></i>
            <p class="mt-2"><?= t('no_categories_found') ?></p>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="clearCategoryForm()">
                <i class="bi bi-plus-lg"></i> <?= t('add_category') ?>
            </button>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px;">#</th>
                        <th><?= t('category_name') ?></th>
                        <th><?= t('description') ?></th>
                        <th class="text-center"><?= t('products') ?></th>
                        <th class="text-center"><?= t('sort_order') ?></th>
                        <th class="text-center" style="width:150px;"><?= t('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $i => $category): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td><strong><?= e($category['name']) ?></strong></td>
                        <td class="text-muted"><?= e($category['description'] ?? '-') ?></td>
                        <td class="text-center">
                            <span class="badge bg-primary rounded-pill"><?= (int)$category['product_count'] ?></span>
                        </td>
                        <td class="text-center"><?= (int)($category['sort_order'] ?? 0) ?></td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary" title="<?= t('edit') ?>"
                                        onclick='editCategory(<?= json_encode([
                                            "id" => (int)$category["id"],
                                            "name" => $category["name"],
                                            "description" => $category["description"] ?? "",
                                            "sort_order" => (int)($category["sort_order"] ?? 0)
                                        ]) ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" title="<?= t('delete') ?>"
                                        onclick="confirmDeleteCategory(<?= (int)$category['id'] ?>, '<?= e($category['name']) ?>', <?= (int)$category['product_count'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" action="<?= url('?action=delete_category') ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="category_id" id="deleteCategoryId" value="">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger"><i class="bi bi-trash"></i> <?= t('delete_category') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><?= t('confirm_delete_category') ?></p>
                    <strong id="deleteCategoryName"></strong>
                    <div id="deleteCategoryWarning" class="alert alert-warning py-2 mt-2" style="display:none;">
                        <i class="bi bi-exclamation-triangle"></i> <?= t('category_has_products') ?>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash"></i> <?= t('delete') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function clearCategoryForm() {
    document.getElementById('categoryId').value = '';
    document.getElementById('categoryName').value = '';
    document.getElementById('categoryDescription').value = '';
    document.getElementById('categorySortOrder').value = 0;
    document.getElementById('categoryFormCard').style.display = 'block';
    document.getElementById('categoryName').focus();
}

function editCategory(cat) {
    document.getElementById('categoryId').value = cat.id;
    document.getElementById('categoryName').value = cat.name;
    document.getElementById('categoryDescription').value = cat.description || '';
    document.getElementById('categorySortOrder').value = cat.sort_order || 0;
    document.getElementById('categoryFormCard').style.display = 'block';
    document.getElementById('categoryName').focus();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function hideCategoryForm() {
    document.getElementById('categoryFormCard').style.display = 'none';
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryId').value = '';
}

function confirmDeleteCategory(id, name, productCount) {
    document.getElementById('deleteCategoryId').value = id;
    document.getElementById('deleteCategoryName').textContent = name;
    document.getElementById('deleteCategoryWarning').style.display = productCount > 0 ? 'block' : 'none';
    var modal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));
    modal.show();
}
</script>