<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

require_permission('manage_products');

$editProduct = null;

if (isset($_GET['edit'])) {
    $editId = (int) ($_GET['edit'] ?? 0);
    if ($editId > 0) {
        $editStmt = $conn->prepare(
            'SELECT id, category_id, sku, product_name, unit, unit_price, current_stock, min_stock
             FROM products
             WHERE id = ?
             LIMIT 1'
        );
        $editStmt->bind_param('i', $editId);
        $editStmt->execute();
        $editProduct = $editStmt->get_result()->fetch_assoc();
        $editStmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $sku = trim($_POST['sku'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $unit = trim($_POST['unit'] ?? '');
    $unitPrice = (float) ($_POST['unit_price'] ?? 0);
    $initialStock = (float) ($_POST['initial_stock'] ?? 0);
    $currentStock = (float) ($_POST['current_stock'] ?? 0);
    $minStock = (float) ($_POST['min_stock'] ?? 0);

    if ($sku === '' || $productName === '' || $categoryId <= 0 || $unit === '') {
        set_flash('danger', 'Veuillez remplir tous les champs obligatoires du produit.');
        redirect('products.php');
    }

    if ($productId > 0) {
        $stmt = $conn->prepare(
            'UPDATE products
             SET category_id = ?, sku = ?, product_name = ?, unit = ?, unit_price = ?, current_stock = ?, min_stock = ?
             WHERE id = ?'
        );
        $stmt->bind_param('isssdddi', $categoryId, $sku, $productName, $unit, $unitPrice, $currentStock, $minStock, $productId);
        $successMessage = 'Produit mis a jour avec succes.';
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO products (category_id, sku, product_name, unit, unit_price, current_stock, min_stock)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isssddd', $categoryId, $sku, $productName, $unit, $unitPrice, $initialStock, $minStock);
        $successMessage = 'Produit ajoute avec succes.';
    }

    if (!$stmt->execute()) {
        $stmt->close();
        set_flash('danger', 'Impossible d enregistrer ce produit. Le SKU existe peut-etre deja.');
        redirect('products.php');
    }

    $newProductId = $stmt->insert_id;
    $stmt->close();

    if ($productId === 0 && $initialStock > 0) {
        $movementType = 'IN';
        $note = 'Stock initial';
        $userId = (int) current_user()['id'];
        $movementStmt = $conn->prepare(
            'INSERT INTO stock_movements (product_id, user_id, type, quantity, reference_note) VALUES (?, ?, ?, ?, ?)'
        );
        $movementStmt->bind_param('iisds', $newProductId, $userId, $movementType, $initialStock, $note);
        $movementStmt->execute();
        $movementStmt->close();
    }

    set_flash('success', $successMessage);
    redirect('products.php');
}

$categories = $conn->query('SELECT id, category_name FROM categories ORDER BY category_name ASC');
$products = $conn->query(
    "SELECT p.id, p.sku, p.product_name, p.category_id, c.category_name, p.unit, p.unit_price, p.current_stock, p.min_stock
     FROM products p
     INNER JOIN categories c ON c.id = p.category_id
     ORDER BY p.product_name ASC"
);

require __DIR__ . '/partials/header.php';
?>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card stat-card">
            <div class="card-header bg-white border-0 pt-4">
                <h1 class="h4 mb-0"><?= $editProduct ? 'Modifier le produit' : 'Nouveau produit' ?></h1>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="product_id" value="<?= (int) ($editProduct['id'] ?? 0) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="sku">Code SKU</label>
                        <input class="form-control" id="sku" name="sku" value="<?= e($editProduct['sku'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="product_name">Nom du produit</label>
                        <input class="form-control" id="product_name" name="product_name" value="<?= e($editProduct['product_name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="category_id">Categorie</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Choisir</option>
                            <?php if ($categories): ?>
                                <?php while ($category = $categories->fetch_assoc()): ?>
                                    <option value="<?= (int) $category['id'] ?>" <?= (int) ($editProduct['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                                        <?= e($category['category_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="unit">Unite</label>
                            <input class="form-control" id="unit" name="unit" placeholder="piece, carton..." value="<?= e($editProduct['unit'] ?? '') ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="unit_price">Prix unitaire</label>
                            <input class="form-control" id="unit_price" name="unit_price" type="number" min="0" step="0.01" value="<?= e(isset($editProduct['unit_price']) ? (string) $editProduct['unit_price'] : '') ?>" required>
                        </div>
                        <?php if ($editProduct): ?>
                            <div class="col-sm-6">
                                <label class="form-label" for="current_stock">Stock actuel</label>
                                <input class="form-control" id="current_stock" name="current_stock" type="number" min="0" step="0.01" value="<?= e((string) $editProduct['current_stock']) ?>" required>
                            </div>
                        <?php else: ?>
                            <div class="col-sm-6">
                                <label class="form-label" for="initial_stock">Stock initial</label>
                                <input class="form-control" id="initial_stock" name="initial_stock" type="number" min="0" step="0.01" value="0">
                            </div>
                        <?php endif; ?>
                        <div class="col-sm-6">
                            <label class="form-label" for="min_stock">Stock minimum</label>
                            <input class="form-control" id="min_stock" name="min_stock" type="number" min="0" step="0.01" value="<?= e(isset($editProduct['min_stock']) ? (string) $editProduct['min_stock'] : '0') ?>" required>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit"><?= $editProduct ? 'Enregistrer' : 'Ajouter le produit' ?></button>
                        <?php if ($editProduct): ?>
                            <a class="btn btn-outline-secondary" href="products.php">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card stat-card">
            <div class="card-header bg-white border-0 pt-4">
                <h2 class="h4 mb-0">Catalogue produits</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Produit</th>
                                <th>Categorie</th>
                                <th>Prix</th>
                                <th>Stock</th>
                                <th>Min</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($products && $products->num_rows > 0): ?>
                                <?php while ($product = $products->fetch_assoc()): ?>
                                    <?php $isLow = (float) $product['current_stock'] <= (float) $product['min_stock']; ?>
                                    <tr>
                                        <td><?= e($product['sku']) ?></td>
                                        <td class="fw-semibold"><?= e($product['product_name']) ?><div class="small text-muted"><?= e($product['unit']) ?></div></td>
                                        <td><?= e($product['category_name']) ?></td>
                                        <td><?= format_number((float) $product['unit_price']) ?></td>
                                        <td class="<?= $isLow ? 'text-danger fw-bold' : '' ?>"><?= format_number((float) $product['current_stock']) ?></td>
                                        <td><?= format_number((float) $product['min_stock']) ?></td>
                                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="products.php?edit=<?= (int) $product['id'] ?>">Modifier</a></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-muted">Aucun produit enregistre.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
