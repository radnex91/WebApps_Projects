<?php
// models/Sale.php
class Sale extends BaseModel {
    protected string $table = 'sales';

    public function createSale(array $saleData, array $items): int {
        $this->db->beginTransaction();
        try {
            $saleId = $this->insert($saleData);
            $stockModel = new Stock();

            foreach ($items as $item) {
                $this->db->prepare(
                    "INSERT INTO sale_items (sale_id, product_id, product_name, barcode, quantity, unit_price, cost_price, discount_percent, discount_amount, total_price)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $saleId, $item['product_id'], $item['product_name'], $item['barcode'] ?? null,
                    $item['quantity'], $item['unit_price'], $item['cost_price'] ?? 0,
                    $item['discount_percent'] ?? 0, $item['discount_amount'] ?? 0, $item['total_price']
                ]);

                // Déduire du stock
                $stockModel->removeStock(
                    (int)$item['product_id'],
                    (int)$saleData['warehouse_id'],
                    (float)$item['quantity'],
                    (int)$saleData['user_id'],
                    'sale',
                    'INV-' . $saleId
                );
            }

            // Mettre à jour les achats client
            if (!empty($saleData['customer_id'])) {
                $custModel = new Customer();
                $custModel->updateTotalPurchases((int)$saleData['customer_id'], (float)$saleData['total_amount']);
            }

            $this->db->commit();
            return $saleId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getSaleWithItems(int $saleId): ?array {
        $sale = $this->queryOne(
            "SELECT s.*, u.name as cashier_name, st.name as store_name, w.name as warehouse_name,
             c.name as customer_name, c.phone as customer_phone
             FROM sales s
             JOIN users u ON s.user_id = u.id
             JOIN stores st ON s.store_id = st.id
             JOIN warehouses w ON s.warehouse_id = w.id
             LEFT JOIN customers c ON s.customer_id = c.id
             WHERE s.id = ?",
            [$saleId]
        );
        if (!$sale) return null;

        $sale['items'] = $this->query(
            "SELECT si.*, p.image FROM sale_items si LEFT JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?",
            [$saleId]
        );
        return $sale;
    }

    public function getByStore(int $storeId, int $page = 1, string $dateFrom = '', string $dateTo = ''): array {
        $offset = ($page - 1) * ITEMS_PER_PAGE;
        $where = "s.store_id = ?";
        $params = [$storeId];

        if ($dateFrom) { $where .= " AND DATE(s.sale_date) >= ?"; $params[] = $dateFrom; }
        if ($dateTo)   { $where .= " AND DATE(s.sale_date) <= ?"; $params[] = $dateTo; }

        $params[] = ITEMS_PER_PAGE;
        $params[] = $offset;

        return $this->query(
            "SELECT s.*, u.name as cashier_name, c.name as customer_name, w.name as warehouse_name
             FROM sales s
             JOIN users u ON s.user_id = u.id
             JOIN warehouses w ON s.warehouse_id = w.id
             LEFT JOIN customers c ON s.customer_id = c.id
             WHERE $where ORDER BY s.sale_date DESC LIMIT ? OFFSET ?",
            $params
        );
    }

    public function getDailyStats(int $storeId, string $date = ''): array {
        if (!$date) $date = date('Y-m-d');
        return $this->queryOne(
            "SELECT COUNT(*) as total_sales, SUM(total_amount) as revenue,
             SUM(total_amount - discount_amount) as net_revenue,
             AVG(total_amount) as avg_sale
             FROM sales WHERE store_id = ? AND DATE(sale_date) = ? AND status = 'completed'",
            [$storeId, $date]
        ) ?? ['total_sales' => 0, 'revenue' => 0, 'net_revenue' => 0, 'avg_sale' => 0];
    }

    public function getMonthlyRevenue(int $storeId, int $year): array {
        return $this->query(
            "SELECT MONTH(sale_date) as month, SUM(total_amount) as revenue, COUNT(*) as count
             FROM sales WHERE store_id = ? AND YEAR(sale_date) = ? AND status = 'completed'
             GROUP BY MONTH(sale_date) ORDER BY month",
            [$storeId, $year]
        );
    }

    public function getTopProducts(int $storeId, int $limit = 10, string $dateFrom = '', string $dateTo = ''): array {
        $where = "s.store_id = ? AND s.status = 'completed'";
        $params = [$storeId];
        if ($dateFrom) { $where .= " AND DATE(s.sale_date) >= ?"; $params[] = $dateFrom; }
        if ($dateTo)   { $where .= " AND DATE(s.sale_date) <= ?"; $params[] = $dateTo; }
        $params[] = $limit;

        return $this->query(
            "SELECT si.product_id, si.product_name, SUM(si.quantity) as total_qty,
             SUM(si.total_price) as total_revenue,
             SUM((si.unit_price - si.cost_price) * si.quantity) as total_profit
             FROM sale_items si JOIN sales s ON si.sale_id = s.id
             WHERE $where
             GROUP BY si.product_id, si.product_name
             ORDER BY total_revenue DESC LIMIT ?",
            $params
        );
    }

    public function getRevenueByStore(): array {
        return $this->query(
            "SELECT st.name as store_name, COUNT(s.id) as total_sales,
             SUM(s.total_amount) as revenue
             FROM sales s JOIN stores st ON s.store_id = st.id
             WHERE s.status = 'completed'
             GROUP BY s.store_id, st.name ORDER BY revenue DESC"
        );
    }
}

class Transfer extends BaseModel {
    protected string $table = 'transfers';

    public function createTransfer(array $transferData, array $items): int {
        $this->db->beginTransaction();
        try {
            $transferId = $this->insert($transferData);
            foreach ($items as $item) {
                $this->db->prepare(
                    "INSERT INTO transfer_items (transfer_id, product_id, quantity_requested, quantity_transferred) VALUES (?, ?, ?, 0)"
                )->execute([$transferId, $item['product_id'], $item['quantity']]);
            }
            $this->db->commit();
            return $transferId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function completeTransfer(int $transferId, int $userId): bool {
        $transfer = $this->find($transferId);
        if (!$transfer || $transfer['status'] !== 'pending') return false;

        $this->db->beginTransaction();
        try {
            $items = $this->query(
                "SELECT * FROM transfer_items WHERE transfer_id = ?",
                [$transferId]
            );
            $stockModel = new Stock();

            foreach ($items as $item) {
                $qty = (float)$item['quantity_requested'];
                // Retirer du stock source
                $stockModel->removeStock(
                    (int)$item['product_id'],
                    (int)$transfer['from_warehouse_id'],
                    $qty, $userId, 'transfer_out', $transfer['reference']
                );
                // Ajouter au stock destination
                $stockModel->addStock(
                    (int)$item['product_id'],
                    (int)$transfer['to_warehouse_id'],
                    $qty, $userId, 'transfer_in', $transfer['reference']
                );
                // Mettre à jour quantity_transferred
                $this->db->prepare(
                    "UPDATE transfer_items SET quantity_transferred = quantity_requested WHERE transfer_id = ? AND product_id = ?"
                )->execute([$transferId, $item['product_id']]);
            }

            $this->update($transferId, [
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s')
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getWithDetails(int $limit = 50): array {
        return $this->query(
            "SELECT t.*, wf.name as from_warehouse, wt.name as to_warehouse,
             sf.name as from_store, st.name as to_store, u.name as user_name
             FROM transfers t
             JOIN warehouses wf ON t.from_warehouse_id = wf.id
             JOIN warehouses wt ON t.to_warehouse_id = wt.id
             JOIN stores sf ON wf.store_id = sf.id
             JOIN stores st ON wt.store_id = st.id
             JOIN users u ON t.user_id = u.id
             ORDER BY t.created_at DESC LIMIT ?",
            [$limit]
        );
    }
}
