<?php
// ButcheryPOS Configuration
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'butcherypos',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'ButcheryPOS',
        'url' => 'http://localhost/ButcheryPOS',
        'base_path' => '/ButcheryPOS',
        'timezone' => 'Africa/Douala',
        'debug' => true,
    ],
    'scale' => [
        'enabled' => false,
        'api_key' => 'CHANGE_ME_SCALE_KEY',
        'device_name' => 'Dibal Scale Bridge',
        'poll_interval_ms' => 1500,
        'protocol' => 'dibal',
        'serial_port' => 'COM3',
        'baud_rate' => 9600,
    ],
    'security' => [
        'session_lifetime' => 28800, // 8 hours
        'csrf_token_length' => 32,
        'max_login_attempts' => 5,
        'login_lockout_minutes' => 15,
        'password_min_length' => 6,
    ],
    'expiry' => [
        'warning_days' => 3,
        'critical_days' => 1,
    ],
    'currency' => 'XAF',
    'default_language' => 'fr',
];