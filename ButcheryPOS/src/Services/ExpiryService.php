<?php
namespace App\Services;

use App\Models\ExpiryAlert;
use App\Models\StockBatch;
use App\Core\Auth;
use App\Repositories\BaseRepository;

class ExpiryService
{
    private BaseRepository $repo;
    private ExpiryAlert $alertModel;
    private StockBatch $batchModel;

    public function __construct(BaseRepository $repo, ExpiryAlert $alertModel, StockBatch $batchModel)
    {
        $this->repo = $repo;
        $this->alertModel = $alertModel;
        $this->batchModel = $batchModel;
    }

    /**
     * Generate expiry alerts for all active batches
     */
    public function generateAlerts(): int
    {
        $warningDays = (int)($GLOBALS['appSettings']['expiry_warning_days'] ?? 3);
        $criticalDays = (int)($GLOBALS['appSettings']['expiry_critical_days'] ?? 1);

        $batches = $this->repo->query(
            "SELECT b.*, p.name AS product_name
             FROM stock_batches b
             INNER JOIN products p ON p.id = b.product_id
             WHERE b.is_fully_consumed = 0
               AND b.expiry_date IS NOT NULL
               AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL :warn_days DAY)
             ORDER BY b.expiry_date ASC",
            ['warn_days' => $warningDays]
        );

        $created = 0;
        foreach ($batches as $batch) {
            $days = (int)((strtotime($batch['expiry_date']) - strtotime('today')) / 86400);
            $type = $days <= 0 ? 'expired' : ($days <= $criticalDays ? 'critical' : 'warning');

            $this->repo->statement(
                "INSERT INTO expiry_alerts (batch_id, product_id, alert_type, days_until_expiry)
                 VALUES (:batch_id, :product_id, :type, :days)
                 ON DUPLICATE KEY UPDATE
                     days_until_expiry = :days2, is_dismissed = 0,
                     dismissed_by = NULL, dismissed_at = NULL",
                [
                    'batch_id' => $batch['id'],
                    'product_id' => $batch['product_id'],
                    'type' => $type,
                    'days' => $days,
                    'days2' => $days,
                ]
            );
            $created++;
        }
        return $created;
    }

    /**
     * Get active (undismissed) alerts
     */
    public function getActiveAlerts(): array
    {
        return $this->repo->query(
            "SELECT ea.*, p.name AS product_name, sb.batch_reference, sb.quantity_remaining,
                     sb.expiry_date, u.abbreviation AS unit_abbr
             FROM expiry_alerts ea
             INNER JOIN products p ON p.id = ea.product_id
             INNER JOIN stock_batches sb ON sb.id = ea.batch_id
             LEFT JOIN units u ON u.id = (SELECT unit_id FROM products WHERE id = ea.product_id)
             WHERE ea.is_dismissed = 0
             ORDER BY FIELD(ea.alert_type, 'expired', 'critical', 'warning'), ea.days_until_expiry ASC"
        );
    }

    /**
     * Dismiss an alert
     */
    public function dismissAlert(int $alertId): bool
    {
        return (bool)$this->repo->statement(
            "UPDATE expiry_alerts SET is_dismissed = 1, dismissed_by = :uid, dismissed_at = NOW() WHERE id = :id",
            ['uid' => Auth::get('id'), 'id' => $alertId]
        );
    }

    /**
     * Mark an expired batch as waste
     */
    public function markAsWaste(int $batchId, int $createdBy): bool
    {
        $batch = $this->batchModel->find($batchId);
        if (!$batch || $batch['is_fully_consumed']) return false;

        $this->repo->beginTransaction();
        try {
            $quantity = (float)$batch['quantity_remaining'];
            $productId = (int)$batch['product_id'];

            // Mark batch as consumed
            $this->batchModel->update($batchId, [
                'quantity_remaining' => 0,
                'is_fully_consumed' => 1,
            ]);

            // Get product current stock
            $product = $this->repo->find('products', $productId);
            $before = (float)$product['quantity_in_stock'];
            $after = $before - $quantity;

            // Create waste movement
            $this->repo->insert('stock_movements', [
                'product_id' => $productId,
                'batch_id' => $batchId,
                'movement_type' => 'waste',
                'quantity' => -$quantity,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'unit_cost' => $batch['unit_cost'],
                'reference_table' => 'stock_batches',
                'reference_id' => $batchId,
                'reason' => 'Expired - marked as waste',
                'created_by' => $createdBy,
            ]);

            // Update product stock
            $this->repo->update('products', $productId, ['quantity_in_stock' => $after]);

            // Dismiss related alerts
            $this->repo->statement(
                "UPDATE expiry_alerts SET is_dismissed = 1, dismissed_by = :uid, dismissed_at = NOW() WHERE batch_id = :bid AND is_dismissed = 0",
                ['uid' => $createdBy, 'bid' => $batchId]
            );

            $this->repo->commit();
            return true;
        } catch (\Exception $e) {
            $this->repo->rollback();
            throw $e;
        }
    }
}