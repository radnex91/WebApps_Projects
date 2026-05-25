<?php
namespace App\Payments;

final class PaymentGatewayFactory
{
    private array $gateways = [];

    public function __construct(array $settings)
    {
        $this->gateways = [
            'cash' => new CashPayment(),
            'card' => new CardPayment(),
            'orange_money' => new OrangeMoneyGateway($this->extractConfig('orange_money', $settings)),
            'mtn_momo' => new MtnMoMoGateway($this->extractConfig('mtn_momo', $settings)),
            'wave' => new WaveGateway($this->extractConfig('wave', $settings)),
        ];
    }

    public function get(string $method): PaymentGatewayInterface
    {
        return $this->gateways[$method] ?? $this->gateways['cash'];
    }

    public function getAvailable(): array
    {
        return array_keys($this->gateways);
    }

    private function extractConfig(string $gateway, array $settings): array
    {
        return [
            'api_key' => $settings["{$gateway}_api_key"] ?? '',
            'merchant_id' => $settings["{$gateway}_merchant_id"] ?? '',
            'base_url' => $settings["{$gateway}_base_url"] ?? '',
        ];
    }
}