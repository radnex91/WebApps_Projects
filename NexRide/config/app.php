<?php
return [
    'debug'           => true,
    'env'             => 'development',
    'offline'         => [
        'enabled'       => true,
        'sync_interval' => 30,
        'max_retry'     => 5,
    ],
    'pagination'      => [
        'per_page' => 20,
    ],
    'session'         => [
        'lifetime'    => 7200,
        'secure'      => false,
        'http_only'   => true,
    ],
    'mail'            => [
        'from'      => 'noreply@nexride.com',
        'smtp_host' => '',
        'smtp_port' => 587,
    ],
];