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
                //=================//
                // === Testnets ===//
                //=================//
            [
                'code' => 'ethereum_sepolia',
                'name' => 'Ethereum Sepolia',
                'chain_id' => 11155111,
                'coin_type' => '60',
                'native_currency_code' => 'ETH',
                'native_currency_name' => 'Sepolia Ether',
                'is_testnet' => true,
                'explorer_url' => 'https://sepolia.etherscan.io',
                'rpc_url' => env('ETHEREUM_SEPOLIA_RPC_URL'),
                'rpc_driver' => 'evm',
                'metadata' => [
                    'provider' => 'infura',
                    'faucet_url' => 'https://www.alchemy.com/faucets/ethereum-sepolia',
                    'bridge_url' => 'https://app.optimism.io/bridge',
                ],
            ],
            [
                'code' => 'arbitrum_sepolia',
                'name' => 'Arbitrum Sepolia',
                'chain_id' => 421614,
                'coin_type' => '60',
                'native_currency_code' => 'ETH',
                'native_currency_name' => 'Arbitrum Sepolia Ether',
                'is_testnet' => true,
                'explorer_url' => 'https://sepolia.arbiscan.io',
                'rpc_url' => env('ARB_SEPOLIA_RPC_URL'),
                'rpc_driver' => 'evm',
                'metadata' => [
                    'provider' => 'infura',
                    'faucet_url' => 'https://www.alchemy.com/faucets/arbitrum-sepolia',
                    'bridge_url' => 'https://bridge.arbitrum.io',
                ],
            ],
            [
                'code' => 'base_sepolia',
                'name' => 'Base Sepolia',
                'chain_id' => 84532,
                'coin_type' => '60',
                'native_currency_code' => 'ETH',
                'native_currency_name' => 'Base Sepolia Ether',
                'is_testnet' => true,
                'explorer_url' => 'https://sepolia.basescan.org',
                'rpc_url' => env('BASE_SEPOLIA_RPC_URL'),
                'rpc_driver' => 'evm',
                'metadata' => [
                    'provider' => 'infura',
                    'faucet_url' => 'https://www.alchemy.com/faucets/base-sepolia',
                    'bridge_url' => 'https://bridge.base.org',
                ],
            ],
            [
                'code' => 'polygon_amoy',
                'name' => 'Polygon Amoy',
                'chain_id' => 80002,
                'coin_type' => '60',
                'native_currency_code' => 'MATIC',
                'native_currency_name' => 'Polygon Amoy MATIC',
                'is_testnet' => true,
                'explorer_url' => 'https://amoy.polygonscan.com',
                'rpc_url' => env('POLYGON_AMOY_RPC_URL'),
                'rpc_driver' => 'evm',
                'metadata' => [
                    'provider' => 'infura',
                    'faucet_url' => 'https://www.alchemy.com/faucets/polygon-amoy',
                    'bridge_url' => 'https://portal.polygon.technology',
                ],
            ],
            [
                'code' => 'tron_nile',
                'name' => 'Tron Nile',
                'chain_id' => null,
                'coin_type' => '195',
                'native_currency_code' => 'TRX',
                'native_currency_name' => 'Tron Nile',
                'is_testnet' => true,
                'explorer_url' => 'https://nile.tronscan.org',
                'rpc_url' => env('TRON_NILE_RPC_URL', 'https://nile.trongrid.io'),
                'rpc_driver' => 'tron',
                'metadata' => [
                    'provider' => 'trongrid',
                    'faucet_url' => 'https://nileex.io/join/getJoinPage',
                ],
            ],
            [
                'code' => 'bitcoin_testnet',
                'name' => 'Bitcoin Testnet',
                'chain_id' => null,
                'coin_type' => '1', // BIP44 testnet coin type
                'native_currency_code' => 'BTC',
                'native_currency_name' => 'Bitcoin Testnet',
                'is_testnet' => true,
                'explorer_url' => 'https://live.blockcypher.com/btc-testnet/',
                'rpc_url' => env('BITCOIN_TESTNET_RPC_URL'),
                'rpc_driver' => 'bitcoin',
                'metadata' => [
                    'provider' => 'custom',
                    'faucet_url' => 'https://bitcoinfaucet.uo1.net/',
                ],
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
