<?php
// models/Store.php
class Store extends BaseModel {
    protected string $table = 'stores';
    public function getActive(): array {
        return $this->findAll(['is_active' => 1], 'name ASC');
    }
}

// models/Warehouse.php  -- on regroupe dans ce fichier
// (en production, séparer en fichiers distincts)

class Warehouse extends BaseModel {
    protected string $table = 'warehouses';

    public function getByStore(int $storeId): array {
        return $this->query(
            "SELECT w.*, s.name as store_name FROM warehouses w JOIN stores s ON w.store_id = s.id WHERE w.store_id = ? AND w.is_active = 1 ORDER BY w.is_default DESC, w.name",
            [$storeId]
        );
    }

    public function getAllWithStore(): array {
        return $this->query(
            "SELECT w.*, s.name as store_name FROM warehouses w JOIN stores s ON w.store_id = s.id WHERE w.is_active = 1 ORDER BY s.name, w.name"
        );
    }
}

class Category extends BaseModel {
    protected string $table = 'categories';

    public function getByStore(int $storeId): array {
        return $this->findAll(['store_id' => $storeId, 'is_active' => 1], 'name ASC');
    }
}

class Product extends BaseModel {
    protected string $table = 'products';

    public function getByStore(int $storeId, int $page = 1, string $search = '', int $categoryId = 0): array {
        $offset = ($page - 1) * ITEMS_PER_PAGE;
        $params = [$storeId];
        $where = "p.store_id = ? AND p.is_active = 1";

        if ($search) {
            $where .= " AND (p.name LIKE ? OR p.barcode LIKE ? OR p.sku LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($categoryId > 0) {
            $where .= " AND p.category_id = ?";
            $params[] = $categoryId;
        }

        $sql = "SELECT p.*, c.name as category_name,
                COALESCE(SUM(s.quantity), 0) as total_stock
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN stock s ON p.id = s.product_id
                WHERE $where
                GROUP BY p.id
                ORDER BY p.name
                LIMIT ? OFFSET ?";
        $params[] = ITEMS_PER_PAGE;
        $params[] = $offset;
        return $this->query($sql, $params);
    }

    public function countByStore(int $storeId, string $search = '', int $categoryId = 0): int {
        $params = [$storeId];
        $where = "store_id = ? AND is_active = 1";
        if ($search) {
            $where .= " AND (name LIKE ? OR barcode LIKE ? OR sku LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($categoryId > 0) {
            $where .= " AND category_id = ?";
            $params[] = $categoryId;
        }
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM products WHERE $where");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findByBarcode(string $barcode, int $storeId): ?array {
        return $this->queryOne(
            "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.barcode = ? AND p.store_id = ? AND p.is_active = 1",
            [$barcode, $storeId]
        );
    }

    public function searchForPOS(string $term, int $storeId, int $warehouseId): array {
        return $this->query(
            "SELECT p.id, p.name, p.barcode, p.selling_price, p.cost_price, p.image, p.unit,
             COALESCE(s.quantity, 0) as stock_qty
             FROM products p
             LEFT JOIN stock s ON p.id = s.product_id AND s.warehouse_id = ?
             WHERE p.store_id = ? AND p.is_active = 1
             AND (p.name LIKE ? OR p.barcode LIKE ? OR p.sku LIKE ?)
             LIMIT 20",
            [$warehouseId, $storeId, "%$term%", "%$term%", "%$term%"]
        );
    }

    public function getLowStock(int $storeId): array {
        return $this->query(
            "SELECT p.*, c.name as category_name, w.name as warehouse_name,
             COALESCE(s.quantity, 0) as stock_qty
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN stock s ON p.id = s.product_id
             LEFT JOIN warehouses w ON s.warehouse_id = w.id
             WHERE p.store_id = ? AND p.is_active = 1
             AND COALESCE(s.quantity, 0) <= p.min_stock_alert
             ORDER BY stock_qty ASC",
            [$storeId]
        );
    }
}

class Stock extends BaseModel {
    protected string $table = 'stock';

    public function getProductStock(int $productId, int $warehouseId): float {
        $row = $this->queryOne(
            "SELECT quantity FROM stock WHERE product_id = ? AND warehouse_id = ?",
            [$productId, $warehouseId]
        );
        return $row ? (float)$row['quantity'] : 0.0;
    }

    public function addStock(int $productId, int $warehouseId, float $quantity, int $userId, string $type = 'in', string $note = '', float $unitCost = 0): bool {
        $current = $this->getProductStock($productId, $warehouseId);
        $newQty = $current + $quantity;

        // Upsert stock
        $this->db->prepare(
            "INSERT INTO stock (product_id, warehouse_id, quantity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = quantity + ?"
        )->execute([$productId, $warehouseId, $quantity, $quantity]);

        // Log movement
        $this->db->prepare(
            "INSERT INTO stock_movements (product_id, warehouse_id, user_id, type, quantity, quantity_before, quantity_after, unit_cost, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$productId, $warehouseId, $userId, $type, $quantity, $current, $newQty, $unitCost, $note]);

        return true;
    }

    public function removeStock(int $productId, int $warehouseId, float $quantity, int $userId, string $type = 'out', string $reference = ''): bool {
        $current = $this->getProductStock($productId, $warehouseId);
        if ($current < $quantity) return false;
        $newQty = $current - $quantity;

        $this->db->prepare(
            "UPDATE stock SET quantity = quantity - ? WHERE product_id = ? AND warehouse_id = ?"
        )->execute([$quantity, $productId, $warehouseId]);

        $this->db->prepare(
            "INSERT INTO stock_movements (product_id, warehouse_id, user_id, type, quantity, quantity_before, quantity_after, reference)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$productId, $warehouseId, $userId, $type, $quantity, $current, $newQty, $reference]);

        return true;
    }

    public function getWarehouseStock(int $warehouseId): array {
        return $this->query(
            "SELECT s.*, p.name, p.barcode, p.sku, p.selling_price, p.min_stock_alert, c.name as category_name
             FROM stock s
             JOIN products p ON s.product_id = p.id
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE s.warehouse_id = ?
             ORDER BY p.name",
            [$warehouseId]
        );
    }

    public function getMovements(int $warehouseId, int $limit = 50): array {
        return $this->query(
            "SELECT sm.*, p.name as product_name, u.name as user_name
             FROM stock_movements sm
             JOIN products p ON sm.product_id = p.id
             JOIN users u ON sm.user_id = u.id
             WHERE sm.warehouse_id = ?
             ORDER BY sm.created_at DESC
             LIMIT ?",
            [$warehouseId, $limit]
        );
    }
}

class Customer extends BaseModel {
    protected string $table = 'customers';

    public function getByStore(int $storeId, string $search = ''): array {
        $params = [$storeId];
        $where = "store_id = ?";
        if ($search) {
            $where .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        return $this->query("SELECT * FROM customers WHERE $where ORDER BY name", $params);
    }

    public function getPurchaseHistory(int $customerId): array {
        return $this->query(
            "SELECT s.*, u.name as cashier_name, w.name as warehouse_name
             FROM sales s JOIN users u ON s.user_id = u.id JOIN warehouses w ON s.warehouse_id = w.id
             WHERE s.customer_id = ? ORDER BY s.sale_date DESC LIMIT 50",
            [$customerId]
        );
    }

    public function updateTotalPurchases(int $customerId, float $amount): void {
        $this->execute(
            "UPDATE customers SET total_purchases = total_purchases + ? WHERE id = ?",
            [$amount, $customerId]
        );
    }
}

class Caisse extends BaseModel {
    protected string $table = 'caisses';

    public function getByStore(int $storeId): array {
        return $this->findAll(['store_id' => $storeId, 'is_active' => 1], 'name ASC');
    }
}
