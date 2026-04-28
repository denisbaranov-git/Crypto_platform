<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CurrencyNetworksSeeder extends Seeder
{
    public function run(): void
    {
        // Получаем ID всех сетей
        $ethereumId = DB::table('networks')->where('code', 'ethereum')->value('id');
        $tronId = DB::table('networks')->where('code', 'tron')->value('id');
        $bitcoinId = DB::table('networks')->where('code', 'bitcoin')->value('id');

        // Получаем ID тестовых сетей
        $ethereumSepoliaId = DB::table('networks')->where('code', 'ethereum_sepolia')->value('id');
        $arbitrumSepoliaId = DB::table('networks')->where('code', 'arbitrum_sepolia')->value('id');
        $baseSepoliaId = DB::table('networks')->where('code', 'base_sepolia')->value('id');
        $polygonAmoyId = DB::table('networks')->where('code', 'polygon_amoy')->value('id');
        $tronNileId = DB::table('networks')->where('code', 'tron_nile')->value('id');
        $bitcoinTestnetId = DB::table('networks')->where('code', 'bitcoin_testnet')->value('id');

        // Получаем ID валют
        $ethId = DB::table('currencies')->where('code', 'ETH')->value('id');
        $trxId = DB::table('currencies')->where('code', 'TRX')->value('id');
        $btcId = DB::table('currencies')->where('code', 'BTC')->value('id');
        $usdtId = DB::table('currencies')->where('code', 'USDT')->value('id');
        $maticId = DB::table('currencies')->where('code', 'MATIC')->value('id');
        $usdcId = DB::table('currencies')->where('code', 'USDC')->value('id');

        // Основные записи (mainnet + testnet)
        $rows = [
            // ===== Ethereum Mainnet =====
            [
                'network_id' => $ethereumId,
                'currency_id' => $ethId,
                'decimals' => 18,
                'contract_address' => null,
                'min_confirmations' => 12,
                'min_withdrawal_amount' => '0.001',
                'is_active' => true,
            ],
            [
                'network_id' => $ethereumId,
                'currency_id' => $usdtId,
                'decimals' => 6,
                'contract_address' => env('ETHEREUM_USDT_CONTRACT'),
                'min_confirmations' => 12,
                'min_withdrawal_amount' => '1.000000',
                'is_active' => true,
            ],

            // ===== TRON Mainnet =====
            [
                'network_id' => $tronId,
                'currency_id' => $trxId,
                'decimals' => 6,
                'contract_address' => null,
                'min_confirmations' => 20,
                'min_withdrawal_amount' => '0.000001',
                'is_active' => true,
            ],
            [
                'network_id' => $tronId,
                'currency_id' => $usdtId,
                'decimals' => 6,
                'contract_address' => env('TRON_USDT_CONTRACT'),
                'min_confirmations' => 20,
                'min_withdrawal_amount' => '1.000000',
                'is_active' => true,
            ],

            // ===== Bitcoin Mainnet =====
            [
                'network_id' => $bitcoinId,
                'currency_id' => $btcId,
                'decimals' => 8,
                'contract_address' => null,
                'min_confirmations' => 6,
                'min_withdrawal_amount' => '0.00000001',
                'is_active' => true,
            ],

            // ===== TESTNETS =====

            // Ethereum Sepolia
            [
                'network_id' => $ethereumSepoliaId,
                'currency_id' => $ethId,
                'decimals' => 18,
                'contract_address' => null,
                'min_confirmations' => 3, // Меньше для тестовой сети
                'min_withdrawal_amount' => '0.000000000000000001',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false), // Активна только в тестовом режиме
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
            ],
            [
                'network_id' => $ethereumSepoliaId,
                'currency_id' => $usdtId,
                'decimals' => 6,
                'contract_address' => env('SEPOLIA_USDT_CONTRACT'),
                'min_confirmations' => 3,
                'min_withdrawal_amount' => '1.000000',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
            ],

            // Arbitrum Sepolia
            [
                'network_id' => $arbitrumSepoliaId,
                'currency_id' => $ethId,
                'decimals' => 18,
                'contract_address' => null,
                'min_confirmations' => 3,
                'min_withdrawal_amount' => '0.000000000000000001',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
            ],
            [
                'network_id' => $arbitrumSepoliaId,
                'currency_id' => $usdcId,
                'decimals' => 6,
                'contract_address' => '0x75faf114eafb1BDbe2F0316DF893fd58CE46AA4d',
                'min_confirmations' => 3,
                'min_withdrawal_amount' => '1.000000',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
            ],

            // Base Sepolia
            [
                'network_id' => $baseSepoliaId,
                'currency_id' => $ethId,
                'decimals' => 18,
                'contract_address' => null,
                'min_confirmations' => 3,
                'min_withdrawal_amount' => '0.000000000000000001',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
            ],
            [
                'network_id' => $baseSepoliaId,
                'currency_id' => $usdcId,
                'decimals' => 6,
                'contract_address' => '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
                'min_confirmations' => 3,
                'min_withdrawal_amount' => '1.000000',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
            ],

            // Polygon Amoy
            [
                'network_id' => $polygonAmoyId,
                'currency_id' => $maticId,
                'decimals' => 18,
                'contract_address' => null,
                'min_confirmations' => 3,
                'min_withdrawal_amount' => '0.000000000000000001',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
            ],
            [
                'network_id' => $polygonAmoyId,
                'currency_id' => $usdtId,
                'decimals' => 6,
                'contract_address' => '0x3813e82e6f7098b9583FC0F33a962D02018B6803',
                'min_confirmations' => 3,
                'min_withdrawal_amount' => '1.000000',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
            ],

            // TRON Nile
            [
                'network_id' => $tronNileId,
                'currency_id' => $trxId,
                'decimals' => 6,
                'contract_address' => null,
                'min_confirmations' => 3,
                'min_withdrawal_amount' => '0.000001',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
            ],
            [
                'network_id' => $tronNileId,
                'currency_id' => $usdtId,
                'decimals' => 6,
                'contract_address' => env('NILE_USDT_CONTRACT'),
                'min_confirmations' => 3,
                'min_withdrawal_amount' => '1.000000',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
            ],

            // Bitcoin Testnet
            [
                'network_id' => $bitcoinTestnetId,
                'currency_id' => $btcId,
                'decimals' => 8,
                'contract_address' => null,
                'min_confirmations' => 2,
                'min_withdrawal_amount' => '0.00000001',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
            ],
        ];

        foreach ($rows as $row) {
            // Пропускаем если нет network_id или currency_id
            if (!$row['network_id'] || !$row['currency_id']) {
                // Для тестовых сетей это нормально, если они не настроены
                if (empty($row['is_active'])) {
                    continue;
                }
                throw new RuntimeException('Missing network/currency dependency while seeding currency_networks.');
            }

            // Для USDT/USDC пропускаем если нет адреса контракта (кроме тестовых сетей)
            if (in_array($row['currency_id'], [$usdtId, $usdcId]) && empty($row['contract_address'])) {
                if (empty($row['is_active'])) {
                    continue; // Тестовые сети могут быть неактивны
                }
                // In production you should set contract env vars.
                continue;
            }

            DB::table('currency_networks')->updateOrInsert(
                [
                    'network_id' => $row['network_id'],
                    'currency_id' => $row['currency_id'],
                ],
                [
                    'decimals' => $row['decimals'],
                    'contract_address' => $row['contract_address'],
                    'min_confirmations' => $row['min_confirmations'],
                    'min_deposit_amount' => '0',
                    'min_withdrawal_amount' => $row['min_withdrawal_amount'],
                    'max_withdrawal_amount' => null,
                    'use_finality' => false,
                    'finalization_blocks' => null,
                    'finality_threshold' => null,
                    'is_active' => $row['is_active'] ?? true,
                    'is_deposit_enabled' => $row['is_deposit_enabled'] ?? true,
                    'is_withdrawal_enabled' => $row['is_withdrawal_enabled'] ?? true,
                    'sort_order' => $row['is_active'] ? 0 : 999, // Неактивные в конец
                    'metadata' => json_encode([
                        'is_testnet' => true,
                        'testnet_faucet' => $row['metadata']['faucet_url'] ?? null,
                    ], JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
