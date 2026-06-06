<?php

return [
    'prometheus_url'   => env('PROMETHEUS_URL', 'http://localhost:9091'),
    'alertmanager_url' => env('ALERTMANAGER_URL', 'http://localhost:9093'),
    'uptime_kuma_url'  => env('UPTIME_KUMA_URL', 'http://localhost:3001'),

    'servers' => [
        [
            'name' => 'mock-target-01',
            'ip'   => 'node-exporter',
            'role' => 'Mock Ubuntu Target',
            'env'  => 'production',
        ],
    ],
];
