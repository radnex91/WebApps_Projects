<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

require_permission('manage_movements');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $type = strtoupper(trim($_POST['type'] ?? ''));
    $quantity = (float) ($_POST['quantity'] ?? 0);
    $note = trim($_POST['reference_note'] ?? '');
    $userId = (int) current_user()['id'];

    if ($productId <= 0 || !in_array($type, ['IN', 'OUT', 'ADJUSTMENT'], true) || $quantity <= 0) {
        set_flash('danger', 'Veuillez verifier les informations du mouvement.');
        redirect('movements.php');
    }

    $productStmt = $conn->prepare('SELECT current_stock, product_name FROM products WHERE id = ? LIMIT 1');
    $productStmt->bind_param('i', $productId);
    $productStmt->execute();
    $product = $productStmt->get_result()->fetch_assoc();
    $productStmt->close();

    if (!$product) {
        set_flash('danger', 'Produit introuvable.');
        redirect('movements.php');
    }

    $currentStock = (float) $product['current_stock'];
    $newStock = $currentStock;

    if ($type === 'IN') {
        $newStock += $quantity;
    } elseif ($type === 'OUT') {
        $newStock -= $quantity;
    } else {
        $newStock = $quantity;
    }

    if ($newStock < 0) {
        set_flash('danger', 'Le stock ne peut pas devenir negatif.');
        redirect('movements.php');
    }

    $conn->begin_transaction();

    try {
        $movementStmt = $conn->prepare(
            'INSERT INTO stock_movements (product_id, user_id, type, quantity, reference_note) VALUES (?, ?, ?, ?, ?)'
        );
        $movementStmt->bind_param('iisds', $productId, $userId, $type, $quantity, $note);
        $movementStmt->execute();
        $movementStmt->close();

        $updateStmt = $conn->prepare('UPDATE products SET current_stock = ? WHERE id = ?');
        $updateStmt->bind_param('di', $newStock, $productId);
        $updateStmt->execute();
        $updateStmt->close();

        $conn->commit();
        set_flash('success', 'Mouvement enregistre pour ' . $product['product_name'] . '.');
    } catch (Throwable $exception) {
        $conn->rollback();
        set_flash('danger', 'Erreur lors de l enregistrement du mouvement.');
    }

    redirect('movements.php');
}

$products = $conn->query('SELECT id, sku, product_name, current_stock, unit FROM products ORDER BY product_name ASC');
$history = $conn->query(
    "SELECT sm.created_at, sm.type, sm.quantity, sm.reference_note, p.product_name, p.sku, u.full_name
     FROM stock_movements sm
     INNER JOIN products p ON p.id = sm.product_id
     INNER JOIN users u ON u.id = sm.user_id
     ORDER BY sm.created_at DESC, sm.id DESC
     LIMIT 20"
);

require __DIR__ . '/partials/header.php';
?>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card stat-card">
            <div class="card-header bg-white border-0 pt-4">
                <h1 class="h4 mb-0">Nouveau mouvement</h1>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label" for="product_id">Produit</label>
                        <select class="form-select" id="product_id" name="product_id" required>
                            <option value="">Choisir</option>
                            <?php if ($products): ?>
                                <?php while ($product = $products->fetch_assoc()): ?>
                                    <option value="<?= (int) $product['id'] ?>">
                                        <?= e($product['product_name']) ?> - <?= e($product['sku']) ?> (<?= format_number((float) $product['current_stock']) ?> <?= e($product['unit']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="type">Type</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="IN">Entree</option>
                            <option value="OUT">Sortie</option>
                            <option value="ADJUSTMENT">Ajustement</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="quantity">Quantite</label>
                        <input class="form-control" id="quantity" name="quantity" type="number" min="0.01" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="reference_note">Note / Reference</label>
                        <textarea class="form-control" id="reference_note" name="reference_note" rows="3" placeholder="Bon de livraison, vente, inventaire..."></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Enregistrer</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card stat-card">
            <div class="card-header bg-white border-0 pt-4">
                <h2 class="h4 mb-0">Historique recent</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Produit</th>
                                <th>Type</th>
                                <th>Quantite</th>
                                <th>Utilisateur</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($history && $history->num_rows > 0): ?>
                                <?php while ($row = $history->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= e($row['created_at']) ?></td>
                                        <td><?= e($row['product_name']) ?><div class="small text-muted"><?= e($row['sku']) ?></div></td>
                                        <td><span class="badge text-bg-secondary"><?= e($row['type']) ?></span></td>
                                        <td><?= format_number((float) $row['quantity']) ?></td>
                                        <td><?= e($row['full_name']) ?></td>
                                        <td><?= e($row['reference_note']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-muted">Aucun mouvement disponible.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
