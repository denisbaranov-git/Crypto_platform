<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class NetworksSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'code' => 'ethereum',
                'name' => 'Ethereum',
                'chain_id' => 1,
                'coin_type' => '60',
                'native_currency_code' => 'ETH',
                'native_currency_name' => 'Ethereum',
                'is_testnet' => false,
                'explorer_url' => 'https://etherscan.io',
                'rpc_url' => env('ETHEREUM_RPC_URL'),
                'rpc_driver' => 'evm',
                'metadata' => ['provider' => 'infura'],
            ],
            [
                'code' => 'tron',
                'name' => 'Tron',
                'chain_id' => null,
                'coin_type' => '195',
                'native_currency_code' => 'TRX',
                'native_currency_name' => 'Tron',
                'is_testnet' => false,
                'explorer_url' => 'https://tronscan.org',
                'rpc_url' => env('TRON_RPC_URL'),
                'rpc_driver' => 'tron',
                'metadata' => ['provider' => 'custom'],
            ],
            [
                'code' => 'bitcoin',
                'name' => 'Bitcoin',
                'chain_id' => null,
                'coin_type' => '0',
                'native_currency_code' => 'BTC',
                'native_currency_name' => 'Bitcoin',
                'is_testnet' => false,
                'explorer_url' => 'https://www.blockchain.com/explorer',
                'rpc_url' => env('BITCOIN_RPC_URL'),
                'rpc_driver' => 'bitcoin',
                'metadata' => ['provider' => 'custom'],
            ],
        ];

        foreach ($rows as $row) {
            if (empty($row['rpc_url'])) {
                throw new RuntimeException("Missing RPC URL for network [{$row['code']}].");
            }

            DB::table('networks')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'chain_id' => $row['chain_id'],
                    'coin_type' => $row['coin_type'],  // 60, 60, 60 (BIP44 coin type)
                    'native_currency_code' => $row['native_currency_code'],
                    'native_currency_name' => $row['native_currency_name'],
                    'is_testnet' => $row['is_testnet'],
                    'explorer_url' => $row['explorer_url'],
                    'rpc_url' => $row['rpc_url'],
                    'rpc_driver' => $row['rpc_driver'],
                    'metadata' => json_encode($row['metadata'], JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
