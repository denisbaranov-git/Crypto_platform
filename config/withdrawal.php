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

    'confirmations' => [
        'default_blocks' => 12,
    ],
];
