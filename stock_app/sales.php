<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

require_permission('manage_sales');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = trim($_POST['customer_name'] ?? '');
    $paidAmount = (float) ($_POST['paid_amount'] ?? 0);
    $productIds = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $userId = (int) current_user()['id'];

    $items = [];
    $grandTotal = 0.0;
    $aggregated = [];

    foreach ($productIds as $index => $rawProductId) {
        $productId = (int) $rawProductId;
        $quantity = (float) ($quantities[$index] ?? 0);

        if ($productId <= 0 || $quantity <= 0) {
            continue;
        }

        if (!isset($aggregated[$productId])) {
            $aggregated[$productId] = 0.0;
        }

        $aggregated[$productId] += $quantity;
    }

    foreach ($aggregated as $productId => $quantity) {
        $stmt = $conn->prepare('SELECT id, product_name, sku, unit_price, current_stock FROM products WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            continue;
        }

        if ((float) $product['current_stock'] < $quantity) {
            set_flash('danger', 'Stock insuffisant pour ' . $product['product_name'] . '.');
            redirect('sales.php');
        }

        $lineTotal = $quantity * (float) $product['unit_price'];
        $grandTotal += $lineTotal;

        $items[] = [
            'product_id' => $productId,
            'product_name' => $product['product_name'],
            'quantity' => $quantity,
            'unit_price' => (float) $product['unit_price'],
            'line_total' => $lineTotal,
            'new_stock' => (float) $product['current_stock'] - $quantity,
        ];
    }

    if ($items === []) {
        set_flash('danger', 'Ajoutez au moins un produit a la vente.');
        redirect('sales.php');
    }

    $saleNumber = 'VTE-' . date('Ymd-His') . '-' . random_int(100, 999);
    $paidAmount = $paidAmount > 0 ? $paidAmount : $grandTotal;

    $conn->begin_transaction();

    try {
        $saleStmt = $conn->prepare(
            'INSERT INTO sales (sale_number, customer_name, total_amount, paid_amount, user_id) VALUES (?, ?, ?, ?, ?)'
        );
        $saleStmt->bind_param('ssddi', $saleNumber, $customerName, $grandTotal, $paidAmount, $userId);
        $saleStmt->execute();
        $saleId = $saleStmt->insert_id;
        $saleStmt->close();

        foreach ($items as $item) {
            $itemStmt = $conn->prepare(
                'INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)'
            );
            $itemStmt->bind_param('iiddd', $saleId, $item['product_id'], $item['quantity'], $item['unit_price'], $item['line_total']);
            $itemStmt->execute();
            $itemStmt->close();

            $productStmt = $conn->prepare('UPDATE products SET current_stock = ? WHERE id = ?');
            $productStmt->bind_param('di', $item['new_stock'], $item['product_id']);
            $productStmt->execute();
            $productStmt->close();

            $movementType = 'OUT';
            $note = 'Vente ' . $saleNumber;
            $moveStmt = $conn->prepare(
                'INSERT INTO stock_movements (product_id, user_id, type, quantity, reference_note) VALUES (?, ?, ?, ?, ?)'
            );
            $moveStmt->bind_param('iisds', $item['product_id'], $userId, $movementType, $item['quantity'], $note);
            $moveStmt->execute();
            $moveStmt->close();
        }

        $conn->commit();
        redirect('sale_ticket.php?id=' . $saleId);
    } catch (Throwable $exception) {
        $conn->rollback();
        set_flash('danger', 'Erreur lors de l enregistrement de la vente.');
        redirect('sales.php');
    }
}

$productOptions = [];
$products = $conn->query(
    'SELECT id, sku, product_name, current_stock, unit_price, unit
     FROM products
     WHERE current_stock > 0
     ORDER BY product_name ASC'
);

if ($products) {
    while ($product = $products->fetch_assoc()) {
        $productOptions[] = $product;
    }
}

$recentSales = $conn->query(
    'SELECT s.id, s.sale_number, s.customer_name, s.total_amount, s.created_at, u.full_name
     FROM sales s
     INNER JOIN users u ON u.id = s.user_id
     ORDER BY s.created_at DESC, s.id DESC
     LIMIT 10'
);

require __DIR__ . '/partials/header.php';
?>
<div class="row g-4">
    <div class="col-xl-6">
        <div class="card stat-card">
            <div class="card-header bg-white border-0 pt-4 d-flex justify-content-between align-items-center">
                <h1 class="h4 mb-0">Nouvelle vente</h1>
                <button class="btn btn-sm btn-outline-primary" type="button" id="add-line-btn">Ajouter une ligne</button>
            </div>
            <div class="card-body">
                <form method="post" id="sale-form">
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label" for="customer_name">Client</label>
                            <input class="form-control" id="customer_name" name="customer_name" placeholder="Client comptoir ou nom du client">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="paid_amount">Montant paye</label>
                            <input class="form-control" id="paid_amount" name="paid_amount" type="number" min="0" step="0.01" placeholder="Auto = total">
                        </div>
                    </div>

                    <div id="sale-lines" class="d-grid gap-3"></div>

                    <div class="border rounded-3 p-3 mt-3 bg-light">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Sous-total</span>
                            <strong id="cart-subtotal">0,00 FCFA</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Articles</span>
                            <strong id="cart-items">0</strong>
                        </div>
                    </div>

                    <button class="btn btn-primary mt-3" type="submit">Valider la vente</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card stat-card mb-4">
            <div class="card-header bg-white border-0 pt-4">
                <h2 class="h4 mb-0">Panier</h2>
            </div>
            <div class="card-body">
                <div id="cart-preview" class="cart-preview-empty text-muted">Aucun article selectionne.</div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="card-header bg-white border-0 pt-4">
                <h2 class="h4 mb-0">Dernieres ventes</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Numero</th>
                                <th>Client</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th>Caissier</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentSales && $recentSales->num_rows > 0): ?>
                                <?php while ($sale = $recentSales->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e($sale['sale_number']) ?></td>
                                        <td><?= e($sale['customer_name'] ?: 'Client comptoir') ?></td>
                                        <td><?= format_number((float) $sale['total_amount']) ?> FCFA</td>
                                        <td><?= e($sale['created_at']) ?></td>
                                        <td><?= e($sale['full_name']) ?></td>
                                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="sale_ticket.php?id=<?= (int) $sale['id'] ?>" target="_blank">Ticket</a></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-muted">Aucune vente enregistree.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="sale-line-template">
    <div class="border rounded-3 p-3 sale-line">
        <div class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Produit</label>
                <select class="form-select sale-product" name="product_id[]">
                    <option value="">Choisir</option>
                    <?php foreach ($productOptions as $product): ?>
                        <option
                            value="<?= (int) $product['id'] ?>"
                            data-name="<?= e($product['product_name']) ?>"
                            data-sku="<?= e($product['sku']) ?>"
                            data-price="<?= e((string) $product['unit_price']) ?>"
                            data-stock="<?= e((string) $product['current_stock']) ?>"
                            data-unit="<?= e($product['unit']) ?>"
                        >
                            <?= e($product['product_name']) ?> - <?= e($product['sku']) ?> - <?= format_number((float) $product['unit_price']) ?> FCFA
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Quantite</label>
                <input class="form-control sale-quantity" type="number" min="0.01" step="0.01" name="quantity[]" value="1">
            </div>
            <div class="col-md-2">
                <div class="small text-muted mb-1">Total ligne</div>
                <div class="fw-bold sale-line-total">0,00 FCFA</div>
                <div class="small text-muted sale-stock-info"></div>
            </div>
            <div class="col-md-1 text-end">
                <button class="btn btn-outline-danger remove-line-btn" type="button">X</button>
            </div>
        </div>
    </div>
</template>

<script>
const saleLines = document.getElementById('sale-lines');
const addLineBtn = document.getElementById('add-line-btn');
const template = document.getElementById('sale-line-template');
const cartPreview = document.getElementById('cart-preview');
const cartSubtotal = document.getElementById('cart-subtotal');
const cartItems = document.getElementById('cart-items');

function formatMoney(value) {
    return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value) + ' FCFA';
}

function createLine() {
    const fragment = template.content.cloneNode(true);
    saleLines.appendChild(fragment);
    updateCart();
}

function updateCart() {
    const lines = Array.from(document.querySelectorAll('.sale-line'));
    let subtotal = 0;
    let itemsCount = 0;
    const preview = [];

    lines.forEach((line) => {
        const select = line.querySelector('.sale-product');
        const qtyInput = line.querySelector('.sale-quantity');
        const totalNode = line.querySelector('.sale-line-total');
        const stockNode = line.querySelector('.sale-stock-info');
        const option = select.options[select.selectedIndex];
        const quantity = parseFloat(qtyInput.value || '0');
        const price = parseFloat(option?.dataset.price || '0');
        const stock = parseFloat(option?.dataset.stock || '0');
        const lineTotal = quantity > 0 ? quantity * price : 0;

        totalNode.textContent = formatMoney(lineTotal);
        stockNode.textContent = option?.value ? 'Stock: ' + stock + ' ' + (option.dataset.unit || '') : '';

        if (option?.value && quantity > 0) {
            subtotal += lineTotal;
            itemsCount += quantity;
            preview.push(
                '<div class="d-flex justify-content-between border-bottom py-2">' +
                '<div><div class="fw-semibold">' + option.dataset.name + '</div><div class="small text-muted">' + quantity + ' x ' + formatMoney(price) + '</div></div>' +
                '<div class="fw-bold">' + formatMoney(lineTotal) + '</div>' +
                '</div>'
            );
        }
    });

    cartSubtotal.textContent = formatMoney(subtotal);
    cartItems.textContent = itemsCount.toString();
    cartPreview.innerHTML = preview.length > 0 ? preview.join('') : '<div class="cart-preview-empty text-muted">Aucun article selectionne.</div>';
}

addLineBtn.addEventListener('click', () => createLine());

saleLines.addEventListener('click', (event) => {
    if (!event.target.classList.contains('remove-line-btn')) {
        return;
    }

    const lines = document.querySelectorAll('.sale-line');
    if (lines.length === 1) {
        lines[0].querySelector('.sale-product').value = '';
        lines[0].querySelector('.sale-quantity').value = '1';
    } else {
        event.target.closest('.sale-line').remove();
    }
    updateCart();
});

saleLines.addEventListener('input', updateCart);
saleLines.addEventListener('change', updateCart);

createLine();
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
