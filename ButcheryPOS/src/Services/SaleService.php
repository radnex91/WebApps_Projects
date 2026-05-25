<?php
namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\PosSession;
use App\Core\Auth;
use App\Repositories\BaseRepository;

class SaleService
{
    private BaseRepository $repo;
    private Sale $saleModel;
    private SaleItem $saleItemModel;
    private PosSession $posSessionModel;
    private StockService $stockService;
    private PaymentService $paymentService;

    public function __construct(
        BaseRepository $repo,
        Sale $saleModel,
        SaleItem $saleItemModel,
        PosSession $posSessionModel,
        StockService $stockService,
        PaymentService $paymentService
    ) {
        $this->repo = $repo;
        $this->saleModel = $saleModel;
        $this->saleItemModel = $saleItemModel;
        $this->posSessionModel = $posSessionModel;
        $this->stockService = $stockService;
        $this->paymentService = $paymentService;
    }

    /**
     * Create a complete sale with FIFO allocation
     * @param array $items [{product_id, quantity, unit_price}]
     * @param array $payments [{method, amount, metadata?}]
     */
    public function createSale(int $posSessionId, array $items, array $payments, ?int $customerId = null, float $discountAmount = 0, ?string $notes = null): array
    {
        $this->repo->beginTransaction();
        try {
            $reference = generate_reference('VTE-');
            $subtotal = 0;
            $saleItems = [];

            // Allocate FIFO batches and calculate totals
            foreach ($items as $item) {
                $allocated = $this->stockService->allocateFifoBatches($item['product_id'], (float)$item['quantity']);
                $itemTotal = (float)$item['quantity'] * (float)$item['unit_price'];
                $unitCost = !empty($allocated) ? $allocated[0]['unit_cost'] : 0;
                $subtotal += $itemTotal;

                $saleItems[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'unit_cost' => $unitCost,
                    'subtotal' => $itemTotal,
                    'allocated' => $allocated,
                ];
            }

            $totalAmount = $subtotal - $discountAmount;

            // Create sale record
            $saleId = $this->saleModel->create([
                'pos_session_id' => $posSessionId,
                'customer_id' => $customerId,
                'reference' => $reference,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => 0,
                'total_amount' => $totalAmount,
                'payment_method' => $payments[0]['method'] ?? 'cash',
                'payment_status' => 'paid',
                'notes' => $notes,
                'created_by' => Auth::get('id'),
            ]);

            // Create sale items and consume stock
            foreach ($saleItems as $si) {
                $batchId = !empty($si['allocated']) ? $si['allocated'][0]['batch_id'] : null;
                $this->saleItemModel->create([
                    'sale_id' => $saleId,
                    'product_id' => $si['product_id'],
                    'batch_id' => $batchId,
                    'quantity' => $si['quantity'],
                    'unit_price' => $si['unit_price'],
                    'unit_cost' => $si['unit_cost'],
                    'subtotal' => $si['subtotal'],
                ]);

                // Consume FIFO batches
                $this->stockService->consumeBatches($si['product_id'], $si['allocated'], $saleId, Auth::get('id'));
            }

            // Record payments
            $totalPaid = 0;
            foreach ($payments as $payment) {
                $this->paymentService->recordPayment($saleId, $payment['method'], (float)$payment['amount'], $payment['metadata'] ?? []);
                $totalPaid += (float)$payment['amount'];
            }

            // Update payment status
            if ($totalPaid < $totalAmount) {
                $this->saleModel->update($saleId, ['payment_status' => 'partial']);
            }

            $this->repo->commit();

            return [
                'success' => true,
                'sale_id' => $saleId,
                'reference' => $reference,
                'total' => $totalAmount,
            ];
        } catch (\Exception $e) {
            $this->repo->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get sale with full details for receipt
     */
    public function getSaleForReceipt(int $saleId): ?array
    {
        return $this->saleModel->withItems($saleId);
    }

    /**
     * Get sales history
     */
    public function getSalesHistory(?string $dateFrom = null, ?string $dateTo = null, ?int $posSessionId = null): array
    {
        $sql = "SELECT s.*, c.name AS customer_name, u.full_name AS created_by_name
                FROM sales s
                LEFT JOIN customers c ON c.id = s.customer_id
                LEFT JOIN users u ON u.id = s.created_by
                WHERE 1=1";
        $params = [];

        if ($dateFrom) {
            $sql .= " AND s.created_at >= :df";
            $params['df'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $sql .= " AND s.created_at <= :dt";
            $params['dt'] = $dateTo . ' 23:59:59';
        }
        if ($posSessionId) {
            $sql .= " AND s.pos_session_id = :psid";
            $params['psid'] = $posSessionId;
        }

        $sql .= " ORDER BY s.created_at DESC LIMIT 100";
        return $this->repo->query($sql, $params);
    }

    /**
     * Get today's sales summary
     */
    public function getTodaySummary(): array
    {
        return $this->repo->query(
            "SELECT COUNT(*) AS total_sales, COALESCE(SUM(total_amount), 0) AS total_revenue,
                    COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END), 0) AS cash_total,
                    COALESCE(SUM(CASE WHEN payment_method != 'cash' THEN total_amount ELSE 0 END), 0) AS other_total
             FROM sales
             WHERE DATE(created_at) = CURDATE()"
        )[0] ?? ['total_sales' => 0, 'total_revenue' => 0, 'cash_total' => 0, 'other_total' => 0];
    }
}