<?php
namespace App\Payments;

final class CashPayment implements PaymentGatewayInterface
{
    public function initiate(float $amount, string $currency, array $metadata = []): array
    {
        return [
            'status' => 'completed',
            'reference' => 'CASH-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)),
            'amount' => $amount,
        ];
    }

    public function verify(string $gatewayReference): array
    {
        return ['status' => 'completed', 'amount' => 0, 'reference' => $gatewayReference];
    }

    public function refund(string $gatewayReference, float $amount): array
    {
        return ['status' => 'refunded', 'reference' => $gatewayReference];
    }

    public function getName(): string { return 'Cash'; }
    public function getIdentifier(): string { return 'cash'; }
}