<?php
namespace App\Services;

use App\Models\Payment;
use App\Payments\PaymentGatewayFactory;
use App\Repositories\BaseRepository;

class PaymentService
{
    private BaseRepository $repo;
    private Payment $paymentModel;
    private PaymentGatewayFactory $gatewayFactory;
    private string $currency;

    public function __construct(BaseRepository $repo, Payment $paymentModel, array $settings)
    {
        $this->repo = $repo;
        $this->paymentModel = $paymentModel;
        $this->gatewayFactory = new PaymentGatewayFactory($settings);
        $this->currency = $settings['company_currency'] ?? 'XAF';
    }

    /**
     * Record a payment for a sale
     */
    public function recordPayment(int $saleId, string $method, float $amount, array $metadata = []): int
    {
        $gateway = $this->gatewayFactory->get($method);
        $result = $gateway->initiate($amount, $this->currency, $metadata);

        return $this->paymentModel->create([
            'sale_id' => $saleId,
            'amount' => $amount,
            'payment_method' => $method,
            'payment_gateway' => $gateway->getIdentifier(),
            'gateway_reference' => $result['reference'] ?? null,
            'gateway_status' => $result['status'] ?? 'pending',
            'gateway_response' => json_encode($result),
        ]);
    }

    /**
     * Get payments for a sale
     */
    public function getSalePayments(int $saleId): array
    {
        return $this->paymentModel->all(['sale_id' => $saleId], 'id ASC');
    }

    /**
     * Calculate total paid for a sale
     */
    public function getTotalPaid(int $saleId): float
    {
        $result = $this->repo->query(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE sale_id = :sid",
            ['sid' => $saleId]
        );
        return (float)($result[0]['total'] ?? 0);
    }
}