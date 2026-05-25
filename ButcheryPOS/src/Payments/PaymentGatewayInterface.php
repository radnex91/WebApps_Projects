<?php
namespace App\Payments;

interface PaymentGatewayInterface
{
    /** Initiate a payment. Returns array with 'status' and 'reference'. */
    public function initiate(float $amount, string $currency, array $metadata = []): array;

    /** Verify a payment by gateway reference. */
    public function verify(string $gatewayReference): array;

    /** Refund a payment. */
    public function refund(string $gatewayReference, float $amount): array;

    /** Get the human-readable name. */
    public function getName(): string;

    /** Get the gateway identifier string. */
    public function getIdentifier(): string;
}