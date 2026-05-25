<?php
namespace App\Scale;

interface ScaleBridgeInterface
{
    /** Get the current weight reading */
    public function getWeight(): float;

    /** Get the device identifier */
    public function getDeviceCode(): string;

    /** Check if the bridge is available */
    public function isAvailable(): bool;
}