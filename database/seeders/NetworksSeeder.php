<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class NetworksSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = [
            // =========================
            // Mainnet
            // =========================
            [
                'code' => 'ethereum',
                'name' => 'Ethereum',
                'chain_id' => 1,
                'coin_type' => 60,
                'native_currency_code' => 'ETH',
                'native_currency_name' => 'Ethereum',
                'is_testnet' => false,
                'explorer_url' => 'https://etherscan.io',
                'rpc_env' => 'ETHEREUM_RPC_URL',
                'rpc_driver' => 'evm',
                'metadata' => ['provider' => 'infura'],
            ],
            [
                'code' => 'arbitrum',
                'name' => 'Arbitrum One',
                'chain_id' => 42161,
                'coin_type' => 60,
                'native_currency_code' => 'ETH',
                'native_currency_name' => 'Ethereum',
                'is_testnet' => false,
                'explorer_url' => 'https://arbiscan.io',
                'rpc_env' => 'ARBITRUM_RPC_URL',
                'rpc_driver' => 'evm',
                'metadata' => ['provider' => 'custom'],
            ],
            [
                'code' => 'base',
                'name' => 'Base',
                'chain_id' => 8453,
                'coin_type' => 60,
                'native_currency_code' => 'ETH',
                'native_currency_name' => 'Ethereum',
                'is_testnet' => false,
                'explorer_url' => 'https://basescan.org',
                'rpc_env' => 'BASE_RPC_URL',
                'rpc_driver' => 'evm',
                'metadata' => ['provider' => 'custom'],
            ],
            [
                'code' => 'polygon',
                'name' => 'Polygon',
                'chain_id' => 137,
                'coin_type' => 60,
                'native_currency_code' => 'MATIC',
                'native_currency_name' => 'Polygon',
                'is_testnet' => false,
                'explorer_url' => 'https://polygonscan.com',
                'rpc_env' => 'POLYGON_RPC_URL',
                'rpc_driver' => 'evm',
                'metadata' => ['provider' => 'custom'],
            ],
            [
                'code' => 'bsc',
                'name' => 'BNB Smart Chain',
                'chain_id' => 56,
                'coin_type' => 60,
                'native_currency_code' => 'BNB',
                'native_currency_name' => 'BNB',
                'is_testnet' => false,
                'explorer_url' => 'https://bscscan.com',
                'rpc_env' => 'BSC_RPC_URL',
                'rpc_driver' => 'evm',
                'metadata' => ['provider' => 'custom'],
            ],
            [
                'code' => 'tron',
                'name' => 'Tron',
                'chain_id' => null,
                'coin_type' => 195,
                'native_currency_code' => 'TRX',
                'native_currency_name' => 'Tron',
                'is_testnet' => false,
                'explorer_url' => 'https://tronscan.org',
                'rpc_env' => 'TRON_RPC_URL',
                'rpc_driver' => 'tron',
                'metadata' => ['provider' => 'custom'],
            ],
            [
                'code' => 'bitcoin',
                'name' => 'Bitcoin',
                'chain_id' => null,
                'coin_type' => 0,
                'native_currency_code' => 'BTC',
                'native_currency_name' => 'Bitcoin',
                'is_testnet' => false,
                'explorer_url' => 'https://www.blockchain.com/explorer',
                'rpc_env' => 'BITCOIN_RPC_URL',
                'rpc_driver' => 'bitcoin',
                'metadata' => ['provider' => 'custom'],
            ],

            // =========================
            // Testnets
            // =========================
            [
                'code' => 'ethereum_sepolia',
                'name' => 'Ethereum Sepolia',
                'chain_id' => 11155111,
                'coin_type' => 60,
                'native_currency_code' => 'ETH',
                'native_currency_name' => 'Sepolia Ether',
                'is_testnet' => true,
                'explorer_url' => 'https://sepolia.etherscan.io',
                'rpc_env' => 'ETHEREUM_SEPOLIA_RPC_URL',
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
                'coin_type' => 60,
                'native_currency_code' => 'ETH',
                'native_currency_name' => 'Arbitrum Sepolia Ether',
                'is_testnet' => true,
                'explorer_url' => 'https://sepolia.arbiscan.io',
                'rpc_env' => 'ARB_SEPOLIA_RPC_URL',
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
                'coin_type' => 60,
                'native_currency_code' => 'ETH',
                'native_currency_name' => 'Base Sepolia Ether',
                'is_testnet' => true,
                'explorer_url' => 'https://sepolia.basescan.org',
                'rpc_env' => 'BASE_SEPOLIA_RPC_URL',
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
                'coin_type' => 60,
                'native_currency_code' => 'MATIC',
                'native_currency_name' => 'Polygon Amoy MATIC',
                'is_testnet' => true,
                'explorer_url' => 'https://amoy.polygonscan.com',
                'rpc_env' => 'POLYGON_AMOY_RPC_URL',
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
                'coin_type' => 195,
                'native_currency_code' => 'TRX',
                'native_currency_name' => 'Tron Nile',
                'is_testnet' => true,
                'explorer_url' => 'https://nile.tronscan.org',
                'rpc_env' => 'TRON_NILE_RPC_URL',
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
                'coin_type' => 1,
                'native_currency_code' => 'BTC',
                'native_currency_name' => 'Bitcoin Testnet',
                'is_testnet' => true,
                'explorer_url' => 'https://live.blockcypher.com/btc-testnet/',
                'rpc_env' => 'BITCOIN_TESTNET_RPC_URL',
                'rpc_driver' => 'bitcoin',
                'metadata' => [
                    'provider' => 'custom',
                    'faucet_url' => 'https://bitcoinfaucet.uo1.net/',
                ],
            ],
        ];

        foreach ($rows as $row) {
            $rpcUrl = $this->requiredEnv($row['rpc_env'], $row['code']);

            DB::table('networks')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'chain_id' => $row['chain_id'],
                    'coin_type' => $row['coin_type'],
                    'native_currency_code' => $row['native_currency_code'],
                    'native_currency_name' => $row['native_currency_name'],
                    'is_testnet' => $row['is_testnet'],
                    'explorer_url' => $row['explorer_url'],
                    'rpc_url' => $rpcUrl,
                    'rpc_driver' => $row['rpc_driver'],
                    'metadata' => json_encode($row['metadata'], JSON_THROW_ON_ERROR),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    private function requiredEnv(string $key, string $networkCode): string
    {
        $value = env($key);

        if (!$value) {
            throw new \RuntimeException("Missing RPC URL [{$key}] required for network [{$networkCode}].");
        }

        return $value;
    }
}
