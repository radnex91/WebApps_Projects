<?php
namespace App\Scale;

class ScaleProtocol
{
    const PROTOCOLS = [
        'dibal' => [
            'baud_rate' => 9600,
            'parity' => 'N',
            'stop_bits' => 1,
            'byte_size' => 8,
            'description' => 'Dibal scales (common in butchery)',
        ],
        'mettler_toledo' => [
            'baud_rate' => 9600,
            'parity' => 'E',
            'stop_bits' => 1,
            'byte_size' => 7,
            'description' => 'Mettler Toledo scales',
        ],
        'avery' => [
            'baud_rate' => 4800,
            'parity' => 'N',
            'stop_bits' => 1,
            'byte_size' => 8,
            'description' => 'Avery scales',
        ],
    ];

    public static function getProtocol(string $name): ?array
    {
        return self::PROTOCOLS[$name] ?? null;
    }

    public static function getAll(): array
    {
        return self::PROTOCOLS;
    }

    public static function getNames(): array
    {
        return array_keys(self::PROTOCOLS);
    }
}