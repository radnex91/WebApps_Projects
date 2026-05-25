<?php
// controllers/sale_controller.php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();
requirePermission('pos');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Méthode non autorisée', null, 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    jsonResponse(false, 'Données JSON invalides', null, 400);
}

// Validation minimale
if (empty($data['items']) || !is_array($data['items'])) {
    jsonResponse(false, 'Panier vide ou invalide', null, 400);
}

$saleData = [
    'invoice_number'  => generateInvoiceNumber((int)($data['store_id'] ?? currentStoreId())),
    'store_id'        => (int)($data['store_id'] ?? currentStoreId()),
    'warehouse_id'    => (int)($data['warehouse_id'] ?? currentWarehouseId()),
    'caisse_id'       => !empty($data['caisse_id']) ? (int)$data['caisse_id'] : (currentCaisseId() > 0 ? currentCaisseId() : null),
    'user_id'         => (int)($_SESSION['user_id']),
    'customer_id'     => !empty($data['customer_id']) ? (int)$data['customer_id'] : null,
    'subtotal'        => (float)($data['subtotal'] ?? 0),
    'discount_amount' => (float)($data['discount_amount'] ?? 0),
    'tax_amount'      => (float)($data['tax_amount'] ?? 0),
    'total_amount'    => (float)($data['total_amount'] ?? 0),
    'paid_amount'     => (float)($data['paid_amount'] ?? 0),
    'change_amount'   => (float)($data['change_amount'] ?? 0),
    'change_refunded' => !empty($data['change_refunded']) ? 1 : 0,
    'payment_method'  => in_array($data['payment_method'] ?? '', ['cash','mobile_money','orange_money','momo','card','credit','mixed'])
        ? $data['payment_method'] : 'cash',
    'status'          => 'completed',
    'notes'           => sanitize($data['notes'] ?? ''),
];

$items = [];
foreach ($data['items'] as $item) {
    $items[] = [
        'product_id'       => (int)($item['product_id'] ?? 0),
        'product_name'     => sanitize($item['product_name'] ?? ''),
        'barcode'          => sanitize($item['barcode'] ?? ''),
        'quantity'         => (float)($item['quantity'] ?? 1),
        'unit_price'       => (float)($item['unit_price'] ?? 0),
        'cost_price'       => (float)($item['cost_price'] ?? 0),
        'discount_percent' => (float)($item['discount_percent'] ?? 0),
        'discount_amount'  => (float)($item['discount_amount'] ?? 0),
        'total_price'      => (float)($item['total_price'] ?? 0),
    ];
}

try {
    $saleModel = new Sale();
    $saleId = $saleModel->createSale($saleData, $items);
    jsonResponse(true, 'Vente enregistrée avec succès', ['sale_id' => $saleId, 'invoice' => $saleData['invoice_number']]);
} catch (Exception $e) {
    jsonResponse(false, 'Erreur: ' . $e->getMessage(), null, 500);
}
