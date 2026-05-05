<?php

declare(strict_types=1);

return [
    'master_mnemonic_encrypted' => env('MASTER_MNEMONIC_ENCRYPTED'),
    'bip39_passphrase' => env('BIP39_PASSPHRASE', ''),

    'ethereum_xpub' => env('ETHEREUM_XPUB'),
    'tron_xpub' => env('TRON_XPUB'),
    'bitcoin_xpub' => env('BITCOIN_XPUB'),

    'arbitrum_xpub' => env('ARBITRUM_XPUB'),
    'base_xpub' => env('BASE_XPUB'),
    'polygon_xpub' => env('POLYGON_XPUB'),
    'bsc_xpub' => env('BSC_XPUB'),

    'ethereum_sepolia_xpub' => env('ETHEREUM_SEPOLIA_XPUB'),
    'tron_nile_xpub' => env('TRON_NILE_XPUB'),
    'bitcoin_testnet_xpub' => env('BITCOIN_TESTNET_XPUB'),
    'arbitrum_sepolia_xpub' => env('ARBITRUM_SEPOLIA_XPUB'),
    'base_sepolia_xpub' => env('BASE_SEPOLIA_XPUB'),
    'polygon_amoy_xpub' => env('POLYGON_AMOY_XPUB'),
];
