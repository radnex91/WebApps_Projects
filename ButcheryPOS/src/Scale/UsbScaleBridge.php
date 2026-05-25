<?php
namespace App\Scale;

class UsbScaleBridge implements ScaleBridgeInterface
{
    public function getWeight(): float
    {
        return 0.0; // Delegates to Python bridge
    }

    public function getDeviceCode(): string
    {
        return 'usb-scale';
    }

    public function isAvailable(): bool
    {
        return false; // Not implemented in Phase 1
    }
}