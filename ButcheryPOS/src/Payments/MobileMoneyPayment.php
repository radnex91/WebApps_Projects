<?php
namespace App\Payments;

abstract class MobileMoneyPayment implements PaymentGatewayInterface
{
    protected string $apiKey;
    protected string $merchantId;
    protected string $baseUrl;

    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->merchantId = $config['merchant_id'] ?? '';
        $this->baseUrl = $config['base_url'] ?? '';
    }

    public function initiate(float $amount, string $currency, array $metadata = []): array
    {
        // Phase 1: Placeholder — record intent, return pending status
        return [
            'status' => 'pending',
            'reference' => $this->getIdentifier() . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)),
            'message' => 'Mobile money payment initiated. Awaiting confirmation.',
            'amount' => $amount,
            'phone' => $metadata['phone'] ?? '',
        ];
    }

    public function verify(string $gatewayReference): array
    {
        return ['status' => 'pending', 'amount' => 0, 'reference' => $gatewayReference];
    }

    public function refund(string $gatewayReference, float $amount): array
    {
        return ['status' => 'pending', 'reference' => $gatewayReference, 'message' => 'Refund not yet implemented.'];
    }
}