<?php

return [
    'prometheus_url' => env('PROMETHEUS_URL', 'http://localhost:9091'),
    'alertmanager_url' => env('ALERTMANAGER_URL', 'http://localhost:9093'),
    'uptime_kuma_url' => env('UPTIME_KUMA_URL', 'http://localhost:3001'),

    'servers' => [
        [
            'name'     => 'mock-target-01',
            'ip'       => 'node-exporter',
            'role'     => 'Mock Ubuntu Target',
            'env'      => 'production',
        ],
        [
            'name'     => 'web-server-01',
            'ip'       => '172.22.2.174',
            'role'     => 'Production Web Node',
            'env'      => 'production',
        ],
        [
            'name'     => 'db-server-01',
            'ip'       => '172.22.2.136',
            'role'     => 'Production DB Node',
            'env'      => 'production',
        ],
    ],
];
