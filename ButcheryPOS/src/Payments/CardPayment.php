<?php
namespace App\Payments;

final class CardPayment implements PaymentGatewayInterface
{
    public function initiate(float $amount, string $currency, array $metadata = []): array
    {
        return [
            'status' => 'completed',
            'reference' => 'CARD-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)),
            'amount' => $amount,
        ];
    }

    public function verify(string $gatewayReference): array
    {
        return ['status' => 'completed', 'amount' => 0, 'reference' => $gatewayReference];
    }

    public function refund(string $gatewayReference, float $amount): array
    {
        return ['status' => 'pending', 'reference' => $gatewayReference, 'message' => 'Card refund pending.'];
    }

    public function getName(): string { return 'Card'; }
    public function getIdentifier(): string { return 'card'; }
}