<?php
// controllers/product_ajax.php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();
if (!hasPermission('pos') && !hasPermission('products')) {
    jsonResponse(false, 'Accès refusé', null, 403);
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'search_pos':
        $term      = sanitize($_GET['search'] ?? '');
        $storeId   = (int)($_GET['store'] ?? currentStoreId());
        $warehouse = (int)($_GET['warehouse'] ?? currentWarehouseId());
        $catId     = (int)($_GET['category'] ?? 0);

        $productModel = new Product();

        $db = Database::getInstance();
        $where = "p.store_id = ? AND p.is_active = 1";
        $params = [$storeId];
        if ($catId > 0) {
            $where .= " AND p.category_id = ?";
            $params[] = $catId;
        }
        if ($term) {
            $where .= " AND (p.name LIKE ? OR p.barcode LIKE ? OR p.sku LIKE ?)";
            $params[] = "%$term%";
            $params[] = "%$term%";
            $params[] = "%$term%";
        }
        $stmt = $db->prepare("SELECT p.id, p.name, p.barcode, p.selling_price, p.cost_price, p.image, p.unit, COALESCE(s.quantity, 0) as stock_qty
            FROM products p LEFT JOIN stock s ON p.id = s.product_id AND s.warehouse_id = ?
            WHERE $where ORDER BY p.name ASC LIMIT 60");
        array_unshift($params, $warehouse);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        jsonResponse(true, '', $products);
        break;

    case 'barcode':
        $barcode = sanitize($_GET['barcode'] ?? '');
        $storeId = (int)($_GET['store'] ?? currentStoreId());
        if (!$barcode) jsonResponse(false, 'Code-barres requis', null, 400);
        $product = (new Product())->findByBarcode($barcode, $storeId);
        if ($product) {
            jsonResponse(true, '', $product);
        } else {
            jsonResponse(false, 'Produit non trouvé', null, 404);
        }
        break;

    default:
        jsonResponse(false, 'Action inconnue', null, 400);
}
