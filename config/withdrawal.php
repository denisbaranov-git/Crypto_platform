<?php

return [
    'scanner' => [
        'default_scan_interval_seconds' => 30,
        'networks' => [
            'ethereum' => [
                'scan_interval_seconds' => 6,
            ],
            'tron' => [
                'scan_interval_seconds' => 5,
            ],
            'bitcoin' => [
                'scan_interval_seconds' => 30,
            ],
        ],
    ],

    'recovery' => [
        'stale_reserved_minutes' => 15,
        'stale_broadcasted_minutes' => 10,
        'stale_settled_minutes' => 10,
    ],
];
