<?php
namespace App\Services;

use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Product;
use App\Core\Auth;
use App\Repositories\BaseRepository;

class StockService
{
    private BaseRepository $repo;
    private StockBatch $batchModel;
    private StockMovement $movementModel;
    private Product $productModel;

    public function __construct(BaseRepository $repo, StockBatch $batchModel, StockMovement $movementModel, Product $productModel)
    {
        $this->repo = $repo;
        $this->batchModel = $batchModel;
        $this->movementModel = $movementModel;
        $this->productModel = $productModel;
    }

    /**
     * Receive stock: creates a batch and a movement, updates product stock
     */
    public function receiveStock(int $productId, float $quantity, float $unitCost, ?string $expiryDate = null, ?int $supplierId = null, ?string $batchRef = null): int
    {
        $this->repo->beginTransaction();
        try {
            $product = $this->productModel->find($productId);
            if (!$product) throw new \Exception('Product not found');

            $quantityBefore = (float)$product['quantity_in_stock'];
            $quantityAfter = $quantityBefore + $quantity;

            // Create batch
            if (!$batchRef) {
                $batchRef = 'BAT-' . date('Ymd') . '-' . str_pad($this->batchModel->count() + 1, 5, '0', STR_PAD_LEFT);
            }
            $batchId = $this->batchModel->create([
                'product_id' => $productId,
                'batch_reference' => $batchRef,
                'quantity_received' => $quantity,
                'quantity_remaining' => $quantity,
                'unit_cost' => $unitCost,
                'expiry_date' => $expiryDate,
                'supplier_id' => $supplierId,
                'is_fully_consumed' => 0,
            ]);

            // Create movement
            $this->movementModel->create([
                'product_id' => $productId,
                'batch_id' => $batchId,
                'movement_type' => 'purchase',
                'quantity' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'unit_cost' => $unitCost,
                'reference_table' => 'stock_batches',
                'reference_id' => $batchId,
                'created_by' => Auth::get('id'),
            ]);

            // Update product stock
            $this->productModel->update($productId, ['quantity_in_stock' => $quantityAfter]);

            $this->repo->commit();
            return $batchId;
        } catch (\Exception $e) {
            $this->repo->rollback();
            throw $e;
        }
    }

    /**
     * Allocate stock batches using FIFO (oldest/expiring first)
     * Must be called within a transaction
     * @return array [{batch_id, quantity_taken, unit_cost}]
     */
    public function allocateFifoBatches(int $productId, float $quantity): array
    {
        $batches = $this->repo->getPdo()->prepare(
            "SELECT * FROM stock_batches
             WHERE product_id = :pid AND is_fully_consumed = 0 AND quantity_remaining > 0
             ORDER BY expiry_date ASC, received_at ASC
             FOR UPDATE"
        );
        $batches->execute(['pid' => $productId]);
        $batches = $batches->fetchAll();

        $allocated = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;

            $take = min($remaining, (float)$batch['quantity_remaining']);
            $allocated[] = [
                'batch_id' => (int)$batch['id'],
                'quantity_taken' => $take,
                'unit_cost' => (float)$batch['unit_cost'],
            ];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new \Exception("Insufficient stock for product ID {$productId}. Short by {$remaining}");
        }

        return $allocated;
    }

    /**
     * Consume allocated batches after a sale
     * Must be called within the same transaction as allocateFifoBatches
     */
    public function consumeBatches(int $productId, array $allocated, int $saleId, int $createdBy): void
    {
        $product = $this->productModel->find($productId);
        $quantityBefore = (float)$product['quantity_in_stock'];
        $totalTaken = 0;

        foreach ($allocated as $alloc) {
            $batch = $this->batchModel->find($alloc['batch_id']);
            $newRemaining = (float)$batch['quantity_remaining'] - $alloc['quantity_taken'];
            $isFullyConsumed = $newRemaining <= 0 ? 1 : 0;

            $this->batchModel->update($alloc['batch_id'], [
                'quantity_remaining' => $newRemaining,
                'is_fully_consumed' => $isFullyConsumed,
            ]);

            $totalTaken += $alloc['quantity_taken'];
        }

        $quantityAfter = $quantityBefore - $totalTaken;

        // Create stock movement for the sale
        $this->movementModel->create([
            'product_id' => $productId,
            'batch_id' => $allocated[0]['batch_id'] ?? null,
            'movement_type' => 'sale',
            'quantity' => -$totalTaken,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'unit_cost' => $allocated[0]['unit_cost'] ?? 0,
            'reference_table' => 'sales',
            'reference_id' => $saleId,
            'created_by' => $createdBy,
        ]);

        // Update product stock
        $this->productModel->update($productId, ['quantity_in_stock' => $quantityAfter]);
    }

    /**
     * Make a stock adjustment (gain/loss)
     */
    public function adjustStock(int $productId, float $quantity, string $type, string $reason, int $createdBy): void
    {
        $this->repo->beginTransaction();
        try {
            $product = $this->productModel->find($productId);
            $before = (float)$product['quantity_in_stock'];
            $after = $type === 'waste' || $type === 'return' ? $before - abs($quantity) : $before + $quantity;

            $this->movementModel->create([
                'product_id' => $productId,
                'movement_type' => $type,
                'quantity' => $type === 'waste' || $type === 'return' ? -abs($quantity) : $quantity,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reason' => $reason,
                'created_by' => $createdBy,
            ]);

            $this->productModel->update($productId, ['quantity_in_stock' => $after]);
            $this->repo->commit();
        } catch (\Exception $e) {
            $this->repo->rollback();
            throw $e;
        }
    }

    /**
     * Get stock movements with filters
     */
    public function getMovements(?int $productId = null, ?string $type = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = "SELECT sm.*, p.name AS product_name, u.full_name AS created_by_name
                FROM stock_movements sm
                INNER JOIN products p ON p.id = sm.product_id
                LEFT JOIN users u ON u.id = sm.created_by
                WHERE 1=1";
        $params = [];

        if ($productId) {
            $sql .= " AND sm.product_id = :pid";
            $params['pid'] = $productId;
        }
        if ($type) {
            $sql .= " AND sm.movement_type = :type";
            $params['type'] = $type;
        }
        if ($dateFrom) {
            $sql .= " AND sm.created_at >= :df";
            $params['df'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $sql .= " AND sm.created_at <= :dt";
            $params['dt'] = $dateTo . ' 23:59:59';
        }

        $sql .= " ORDER BY sm.created_at DESC LIMIT 200";
        return $this->repo->query($sql, $params);
    }

    /**
     * Get batches for a product
     */
    public function getProductBatches(int $productId): array
    {
        return $this->repo->query(
            "SELECT sb.*, s.name AS supplier_name
             FROM stock_batches sb
             LEFT JOIN suppliers s ON s.id = sb.supplier_id
             WHERE sb.product_id = :pid AND sb.is_fully_consumed = 0
             ORDER BY sb.expiry_date ASC, sb.received_at ASC",
            ['pid' => $productId]
        );
    }

    /**
     * Get low-stock products
     */
    public function getLowStockProducts(): array
    {
        return $this->repo->query(
            "SELECT p.*, c.name AS category_name, u.abbreviation AS unit_abbr
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN units u ON u.id = p.unit_id
             WHERE p.is_active = 1 AND p.quantity_in_stock <= p.reorder_level
             ORDER BY p.quantity_in_stock ASC"
        );
    }
}