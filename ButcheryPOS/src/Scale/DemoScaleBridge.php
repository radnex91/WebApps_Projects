<?php
namespace App\Scale;

class DemoScaleBridge implements ScaleBridgeInterface
{
    public function getWeight(): float
    {
        return round(mt_rand(500, 5000) / 1000, 3);
    }

    public function getDeviceCode(): string
    {
        return 'demo-scale';
    }

    public function isAvailable(): bool
    {
        return true;
    }
}