<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

require_permission('manage_sales');

$saleId = (int) ($_GET['id'] ?? 0);

if ($saleId <= 0) {
    redirect('sales.php');
}

$saleStmt = $conn->prepare(
    'SELECT s.id, s.sale_number, s.customer_name, s.total_amount, s.paid_amount, s.created_at, u.full_name
     FROM sales s
     INNER JOIN users u ON u.id = s.user_id
     WHERE s.id = ?
     LIMIT 1'
);
$saleStmt->bind_param('i', $saleId);
$saleStmt->execute();
$sale = $saleStmt->get_result()->fetch_assoc();
$saleStmt->close();

if (!$sale) {
    set_flash('danger', 'Vente introuvable.');
    redirect('sales.php');
}

$itemsStmt = $conn->prepare(
    'SELECT si.quantity, si.unit_price, si.line_total, p.product_name
     FROM sale_items si
     INNER JOIN products p ON p.id = si.product_id
     WHERE si.sale_id = ?
     ORDER BY si.id ASC'
);
$itemsStmt->bind_param('i', $saleId);
$itemsStmt->execute();
$items = $itemsStmt->get_result();
$itemsStmt->close();

$change = (float) $sale['paid_amount'] - (float) $sale['total_amount'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket <?= e($sale['sale_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; }
        .ticket { max-width: 420px; margin: 24px auto; background: #fff; padding: 24px; border: 1px solid #ddd; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .ticket { margin: 0 auto; border: 0; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="ticket shadow-sm">
        <div class="text-center mb-4">
            <h1 class="h4 mb-1">StockPro</h1>
            <div>Ticket de vente</div>
        </div>
        <div class="small mb-3">
            <div><strong>Numero:</strong> <?= e($sale['sale_number']) ?></div>
            <div><strong>Date:</strong> <?= e($sale['created_at']) ?></div>
            <div><strong>Client:</strong> <?= e($sale['customer_name'] ?: 'Client comptoir') ?></div>
            <div><strong>Caissier:</strong> <?= e($sale['full_name']) ?></div>
        </div>
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Article</th>
                    <th>Qte</th>
                    <th>PU</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = $items->fetch_assoc()): ?>
                    <tr>
                        <td><?= e($item['product_name']) ?></td>
                        <td><?= format_number((float) $item['quantity']) ?></td>
                        <td><?= format_number((float) $item['unit_price']) ?></td>
                        <td><?= format_number((float) $item['line_total']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <hr>
        <div class="small">
            <div class="d-flex justify-content-between"><span>Total</span><strong><?= format_number((float) $sale['total_amount']) ?> FCFA</strong></div>
            <div class="d-flex justify-content-between"><span>Paye</span><strong><?= format_number((float) $sale['paid_amount']) ?> FCFA</strong></div>
            <div class="d-flex justify-content-between"><span>Monnaie</span><strong><?= format_number($change > 0 ? $change : 0) ?> FCFA</strong></div>
        </div>
        <div class="text-center mt-4 small">Merci pour votre achat.</div>
        <div class="no-print text-center mt-4 d-flex justify-content-center gap-2">
            <button class="btn btn-primary" onclick="window.print()">Imprimer</button>
            <a class="btn btn-outline-secondary" href="sales.php">Retour</a>
        </div>
    </div>
</body>
</html>
