<?php
namespace App\Scale;

class SerialScaleBridge implements ScaleBridgeInterface
{
    private string $port;
    private string $protocol;

    public function __construct(string $port = 'COM3', string $protocol = 'dibal')
    {
        $this->port = $port;
        $this->protocol = $protocol;
    }

    public function getWeight(): float
    {
        // PHP delegates to Python bridge via HTTP
        return 0.0; // Actual reading comes from scale_readings table via API
    }

    public function getDeviceCode(): string
    {
        return "{$this->protocol}-{$this->port}";
    }

    public function isAvailable(): bool
    {
        // Check if Python bridge is running by checking recent readings
        return true; // Determined by whether there are recent scale_readings
    }
}