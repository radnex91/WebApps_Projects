<?php
// controllers/stock_ajax.php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();
requirePermission('stock_view');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'warehouse_stock':
        $wid = (int)($_GET['warehouse_id'] ?? 0);
        if (!$wid) jsonResponse(false, 'warehouse_id requis', null, 400);
        $stock = (new Stock())->getWarehouseStock($wid);
        jsonResponse(true, '', $stock);
        break;

    case 'search_product':
        $term      = sanitize($_GET['search'] ?? '');
        $storeId   = (int)($_GET['store'] ?? currentStoreId());
        $warehouse = (int)($_GET['warehouse'] ?? currentWarehouseId());

        if (!$term) jsonResponse(false, 'Terme de recherche requis', null, 400);

        $productModel = new Product();
        $products = $productModel->searchForPOS($term, $storeId, $warehouse);

        // Ajouter le stock actuel dans l'entrepôt pour chaque produit
        $stockModel = new Stock();
        foreach ($products as &$p) {
            $p['current_stock'] = $stockModel->getProductStock((int)$p['id'], $warehouse);
        }
        unset($p);

        jsonResponse(true, '', $products);
        break;

    case 'barcode_lookup':
        $barcode   = sanitize($_GET['barcode'] ?? '');
        $storeId   = (int)($_GET['store'] ?? currentStoreId());
        $warehouse = (int)($_GET['warehouse'] ?? currentWarehouseId());

        if (!$barcode) jsonResponse(false, 'Code-barres requis', null, 400);

        $product = (new Product())->findByBarcode($barcode, $storeId);
        if (!$product) {
            jsonResponse(false, 'Produit non trouvé', null, 404);
        }

        // Ajouter le stock actuel dans l'entrepôt
        $product['current_stock'] = (new Stock())->getProductStock((int)$product['id'], $warehouse);
        jsonResponse(true, '', $product);
        break;

    default:
        jsonResponse(false, 'Action inconnue', null, 400);
}