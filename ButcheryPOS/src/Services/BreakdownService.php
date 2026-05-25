<?php
namespace App\Services;

use App\Models\Breakdown;
use App\Models\BreakdownItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Repositories\BaseRepository;

class BreakdownService
{
    private BaseRepository $repo;
    private Breakdown $breakdownModel;
    private BreakdownItem $breakdownItemModel;
    private Product $productModel;
    private StockBatch $batchModel;
    private StockMovement $movementModel;

    public function __construct(
        BaseRepository $repo,
        Breakdown $breakdownModel,
        BreakdownItem $breakdownItemModel,
        Product $productModel,
        StockBatch $batchModel,
        StockMovement $movementModel
    ) {
        $this->repo = $repo;
        $this->breakdownModel = $breakdownModel;
        $this->breakdownItemModel = $breakdownItemModel;
        $this->productModel = $productModel;
        $this->batchModel = $batchModel;
        $this->movementModel = $movementModel;
    }

    /**
     * Record a meat breakdown (découpe): cut a carcass into retail products
     *
     * @param int $sourceProductId  Product ID of the carcass
     * @param int $sourceBatchId    Batch ID to cut from
     * @param float $sourceQuantity Weight taken from source (kg)
     * @param array $outputItems   [{product_id, quantity, is_byproduct}]
     * @param float $wasteWeight   Weight lost/wasted
     * @param string|null $notes   Optional notes
     * @return int Breakdown ID
     * @throws \Exception on validation failure
     */
    public function recordBreakdown(
        int $sourceProductId,
        int $sourceBatchId,
        float $sourceQuantity,
        array $outputItems,
        float $wasteWeight = 0,
        ?string $notes = null
    ): int {
        $pdo = $this->repo->getPdo();

        try {
            $pdo->beginTransaction();

            // 1. Validate source
            $sourceProduct = $this->productModel->find($sourceProductId);
            if (!$sourceProduct) {
                throw new \Exception('Source product not found');
            }

            $sourceBatch = $this->batchModel->find($sourceBatchId);
            if (!$sourceBatch || $sourceBatch['product_id'] != $sourceProductId) {
                throw new \Exception('Source batch not found or does not belong to this product');
            }

            if ($sourceBatch['quantity_remaining'] < $sourceQuantity) {
                throw new \Exception('Insufficient stock in source batch. Available: ' . $sourceBatch['quantity_remaining'] . ' kg');
            }

            $sourceUnitCost = (float)$sourceBatch['unit_cost'];
            $totalOutputWeight = 0;

            // Validate output items
            foreach ($outputItems as $item) {
                if (($item['product_id'] ?? 0) <= 0) continue;
                $outputProduct = $this->productModel->find((int)$item['product_id']);
                if (!$outputProduct) {
                    throw new \Exception('Output product not found: ID ' . $item['product_id']);
                }
                $qty = (float)($item['quantity'] ?? 0);
                if ($qty <= 0) continue;
                $totalOutputWeight += $qty;
            }

            // 2. Weight reconciliation (0.5 kg tolerance)
            $difference = abs($sourceQuantity - ($totalOutputWeight + $wasteWeight));
            if ($difference > 0.5) {
                throw new \Exception(
                    'Weight mismatch: source ' . $sourceQuantity . ' kg vs outputs ' . $totalOutputWeight . ' kg + waste ' . $wasteWeight . ' kg (diff: ' . round($difference, 2) . ' kg). Maximum tolerance: 0.5 kg.'
                );
            }

            // 3. Create breakdown record
            $reference = 'BD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $breakdownId = $this->breakdownModel->create([
                'reference' => $reference,
                'source_product_id' => $sourceProductId,
                'source_batch_id' => $sourceBatchId,
                'source_quantity' => $sourceQuantity,
                'source_unit_cost' => $sourceUnitCost,
                'total_output_weight' => $totalOutputWeight,
                'waste_weight' => $wasteWeight,
                'notes' => $notes,
                'created_by' => \App\Core\Auth::get('id'),
            ]);

            // 4. Deduct from source batch
            $newRemaining = (float)$sourceBatch['quantity_remaining'] - $sourceQuantity;
            $this->batchModel->update($sourceBatchId, [
                'quantity_remaining' => $newRemaining,
                'is_fully_consumed' => $newRemaining <= 0 ? 1 : 0,
            ]);

            // 5. Source stock movement (negative)
            $sourceQtyBefore = (float)$sourceProduct['quantity_in_stock'];
            $sourceQtyAfter = $sourceQtyBefore - $sourceQuantity;
            $this->movementModel->create([
                'product_id' => $sourceProductId,
                'batch_id' => $sourceBatchId,
                'movement_type' => 'breakdown',
                'quantity' => -$sourceQuantity,
                'quantity_before' => $sourceQtyBefore,
                'quantity_after' => $sourceQtyAfter,
                'unit_cost' => $sourceUnitCost,
                'reference_table' => 'breakdowns',
                'reference_id' => $breakdownId,
                'reason' => 'Découpe: ' . $reference,
                'created_by' => \App\Core\Auth::get('id'),
            ]);

            // 6. Update source product stock
            $this->productModel->update($sourceProductId, [
                'quantity_in_stock' => $sourceQtyAfter,
            ]);

            // 7. Process output items
            foreach ($outputItems as $item) {
                $outputProductId = (int)($item['product_id'] ?? 0);
                $outputQuantity = (float)($item['quantity'] ?? 0);
                $isByproduct = (int)($item['is_byproduct'] ?? 0);

                if ($outputProductId <= 0 || $outputQuantity <= 0) continue;

                $outputProduct = $this->productModel->find($outputProductId);
                $outputQtyBefore = (float)$outputProduct['quantity_in_stock'];
                $outputQtyAfter = $outputQtyBefore + $outputQuantity;

                // Create output batch (inherits cost, expiry, supplier from source)
                $outputBatchId = $this->batchModel->create([
                    'product_id' => $outputProductId,
                    'batch_reference' => $reference . '-' . $outputProductId,
                    'quantity_received' => $outputQuantity,
                    'quantity_remaining' => $outputQuantity,
                    'unit_cost' => $sourceUnitCost,
                    'expiry_date' => $sourceBatch['expiry_date'],
                    'supplier_id' => $sourceBatch['supplier_id'],
                    'is_fully_consumed' => 0,
                ]);

                // Output stock movement (positive)
                $this->movementModel->create([
                    'product_id' => $outputProductId,
                    'batch_id' => $outputBatchId,
                    'movement_type' => 'breakdown',
                    'quantity' => $outputQuantity,
                    'quantity_before' => $outputQtyBefore,
                    'quantity_after' => $outputQtyAfter,
                    'unit_cost' => $sourceUnitCost,
                    'reference_table' => 'breakdowns',
                    'reference_id' => $breakdownId,
                    'reason' => 'Découpe: ' . $reference,
                    'created_by' => \App\Core\Auth::get('id'),
                ]);

                // Update output product stock
                $this->productModel->update($outputProductId, [
                    'quantity_in_stock' => $outputQtyAfter,
                ]);

                // Create breakdown item
                $this->breakdownItemModel->create([
                    'breakdown_id' => $breakdownId,
                    'output_product_id' => $outputProductId,
                    'output_batch_id' => $outputBatchId,
                    'output_quantity' => $outputQuantity,
                    'output_unit_cost' => $sourceUnitCost,
                    'is_byproduct' => $isByproduct,
                ]);
            }

            // 8. Waste movement (informational)
            if ($wasteWeight > 0) {
                $this->movementModel->create([
                    'product_id' => $sourceProductId,
                    'batch_id' => null,
                    'movement_type' => 'waste',
                    'quantity' => -$wasteWeight,
                    'quantity_before' => $sourceQtyAfter,
                    'quantity_after' => $sourceQtyAfter,
                    'unit_cost' => $sourceUnitCost,
                    'reference_table' => 'breakdowns',
                    'reference_id' => $breakdownId,
                    'reason' => 'Perte découpe: ' . $reference,
                    'created_by' => \App\Core\Auth::get('id'),
                ]);
            }

            $pdo->commit();
            return $breakdownId;

        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Get recent breakdowns with source product info
     */
    public function getBreakdowns(int $limit = 50): array
    {
        return $this->breakdownModel->getRecentBreakdowns($limit);
    }

    /**
     * Get breakdown detail with items
     */
    public function getBreakdownDetail(int $id): ?array
    {
        return $this->breakdownModel->withItems($id);
    }

    /**
     * Get products marked as carcass (is_carcass = 1)
     */
    public function getCarcassProducts(): array
    {
        return $this->productModel->all(['is_carcass' => 1, 'is_active' => 1], 'name ASC');
    }

    /**
     * Get active batches for a carcass product
     */
    public function getCarcassBatches(int $productId): array
    {
        return $this->batchModel->getRepo()->query(
            "SELECT sb.*, s.name AS supplier_name
             FROM stock_batches sb
             LEFT JOIN suppliers s ON s.id = sb.supplier_id
             WHERE sb.product_id = :pid AND sb.is_fully_consumed = 0 AND sb.quantity_remaining > 0
             ORDER BY sb.expiry_date ASC, sb.received_at ASC",
            ['pid' => $productId]
        );
    }

    /**
     * Get all retail products (non-carcass, active) for output dropdown
     */
    public function getRetailProducts(): array
    {
        return $this->productModel->getRepo()->query(
            "SELECT p.*, c.name AS category_name, u.abbreviation AS unit_abbr
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN units u ON u.id = p.unit_id
             WHERE p.is_active = 1 AND (p.is_carcass = 0 OR p.is_carcass IS NULL)
             ORDER BY p.name ASC"
        );
    }
}