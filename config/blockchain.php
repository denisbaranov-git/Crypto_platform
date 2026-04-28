<?php

return [

    'scanner' => [
        'default_safety_margin_blocks' => 12,
        'default_scan_interval_seconds' => 30,

        'networks' => [
            'ethereum' => [
                'scan_interval_seconds' => 6,
                'safety_margin_blocks'   => 12,
                'reorg_window_blocks'    => 50,
            ],

            'tron' => [
                'scan_interval_seconds' => 5,
                'safety_margin_blocks'   => 20,
                'reorg_window_blocks'    => 50,
            ],

            'bitcoin' => [
                'scan_interval_seconds' => 30,
                'safety_margin_blocks'   => 6,
                'reorg_window_blocks'    => 100,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Confirmation rules
    |--------------------------------------------------------------------------
    */

    'confirmations' => [
        'default_blocks' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | EVM specifics
    |--------------------------------------------------------------------------
    */

    'evm' => [
        'transfer_signature' => '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',
    ],

    'api' =>[
        'ethereum' => [
            'rpc_url' => env('ETHEREUM_RPC_URL'),
            'api_key' => env('ETHEREUM_PRO_API_KEY'),
        ],
        'tron' => [
            'rpc_url' => env('TRON_RPC_URL'),
            'api_key' => env('TRON_PRO_API_KEY'),
        ],
        'bitcoin' => [],
    ]

];
