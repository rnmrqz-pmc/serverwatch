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
        [
            'name' => 'web-01',
            'ip'   => '172.22.2.171',
            'role' => 'Production Web Node',
            'env'  => 'production',
        ],
        [
            'name' => 'web-02',
            'ip'   => '172.22.2.172',
            'role' => 'Production Web Node',
            'env'  => 'production',
        ],
        [
            'name' => 'web-03',
            'ip'   => '172.22.2.131',
            'role' => 'Production Web Node',
            'env'  => 'production',
        ],
        [
            'name' => 'web-04',
            'ip'   => '172.22.2.132',
            'role' => 'Production Web Node',
            'env'  => 'production',
        ],
        [
            'name' => 'web-05',
            'ip'   => '172.22.2.151',
            'role' => 'Production Web Node',
            'env'  => 'production',
        ],
        [
            'name' => 'web-06',
            'ip'   => '172.22.2.152',
            'role' => 'Production Web Node',
            'env'  => 'production',
        ],
        [
            'name' => 'web-07',
            'ip'   => '172.22.2.141',
            'role' => 'Production Web Node',
            'env'  => 'production',
        ],
        [
            'name' => 'web-08',
            'ip'   => '172.22.2.142',
            'role' => 'Production Web Node',
            'env'  => 'production',
        ],
    ],
];
