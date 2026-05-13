<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class CurrencyNetworksSeeder extends Seeder
{
    public function run(): void
    {
        $networkIds = $this->loadNetworkIds();
        $currencyIds = $this->loadCurrencyIds();

        $rows = [
            // =========================
            // Mainnet
            // =========================
            [
                'network_code' => 'ethereum',
                'currency_code' => 'ETH',
                'decimals' => 18,
                'contract_address' => null,
                'min_confirmations' => 12,
                'min_withdrawal_amount' => '0.001000000000000000',
                'is_active' => true,
                'is_deposit_enabled' => true,
                'is_withdrawal_enabled' => true,
                'sort_order' => 0,
                'metadata' => ['provider' => 'native'],
            ],
            [
                'network_code' => 'ethereum',
                'currency_code' => 'USDT',
                'decimals' => 6,
                'contract_address' => env('ETHEREUM_USDT_CONTRACT'),
                'min_confirmations' => 12,
                'min_withdrawal_amount' => '1.000000',
                'is_active' => true,
                'is_deposit_enabled' => true,
                'is_withdrawal_enabled' => true,
                'sort_order' => 1,
                'metadata' => ['provider' => 'token'],
            ],
            [
                'network_code' => 'arbitrum',
                'currency_code' => 'ETH',
                'decimals' => 18,
                'contract_address' => null,
                'min_confirmations' => 12,
                'min_withdrawal_amount' => '0.001000000000000000',
                'is_active' => true,
                'is_deposit_enabled' => true,
                'is_withdrawal_enabled' => true,
                'sort_order' => 0,
                'metadata' => ['provider' => 'native'],
            ],
            [
                'network_code' => 'base',
                'currency_code' => 'ETH',
                'decimals' => 18,
                'contract_address' => null,
                'min_confirmations' => 12,
                'min_withdrawal_amount' => '0.001000000000000000',
                'is_active' => true,
                'is_deposit_enabled' => true,
                'is_withdrawal_enabled' => true,
                'sort_order' => 0,
                'metadata' => ['provider' => 'native'],
            ],
            [
                'network_code' => 'polygon',
                'currency_code' => 'MATIC',
                'decimals' => 18,
                'contract_address' => null,
                'min_confirmations' => 12,
                'min_withdrawal_amount' => '0.001000000000000000',
                'is_active' => true,
                'is_deposit_enabled' => true,
                'is_withdrawal_enabled' => true,
                'sort_order' => 0,
                'metadata' => ['provider' => 'native'],
            ],
            [
                'network_code' => 'bsc',
                'currency_code' => 'BNB',
                'decimals' => 18,
                'contract_address' => null,
                'min_confirmations' => 12,
                'min_withdrawal_amount' => '0.001000000000000000',
                'is_active' => true,
                'is_deposit_enabled' => true,
                'is_withdrawal_enabled' => true,
                'sort_order' => 0,
                'metadata' => ['provider' => 'native'],
            ],
            [
                'network_code' => 'tron',
                'currency_code' => 'TRX',
                'decimals' => 6,
                'contract_address' => null,
                'min_confirmations' => 20,
                'min_withdrawal_amount' => '0.000001',
                'is_active' => true,
                'is_deposit_enabled' => true,
                'is_withdrawal_enabled' => true,
                'sort_order' => 0,
                'metadata' => ['provider' => 'native'],
            ],
            [
                'network_code' => 'tron',
                'currency_code' => 'USDT',
                'decimals' => 6,
                'contract_address' => env('TRON_USDT_CONTRACT'),
                'min_confirmations' => 20,
                'min_withdrawal_amount' => '1.000000',
                'is_active' => true,
                'is_deposit_enabled' => true,
                'is_withdrawal_enabled' => true,
                'sort_order' => 1,
                'metadata' => ['provider' => 'token'],
            ],
            [
                'network_code' => 'bitcoin',
                'currency_code' => 'BTC',
                'decimals' => 8,
                'contract_address' => null,
                'min_confirmations' => 6,
                'min_withdrawal_amount' => '0.00000001',
                'is_active' => true,
                'is_deposit_enabled' => true,
                'is_withdrawal_enabled' => true,
                'sort_order' => 0,
                'metadata' => ['provider' => 'native'],
            ],

            // =========================
            // Testnets
            // =========================
            [
                'network_code' => 'ethereum_sepolia',
                'currency_code' => 'ETH',
                'decimals' => 18,
                'contract_address' => null,
                'min_confirmations' => 3,
                'min_withdrawal_amount' => '0.000000000000000001',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'sort_order' => 0,
                'metadata' => ['provider' => 'native', 'is_testnet' => true],
            ],
            [
                'network_code' => 'ethereum_sepolia',
                'currency_code' => 'USDT',
                'decimals' => 6,
                'contract_address' => env('SEPOLIA_USDT_CONTRACT'),
                'min_confirmations' => 3,
                'min_withdrawal_amount' => '1.000000',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'sort_order' => 1,
                'metadata' => ['provider' => 'token', 'is_testnet' => true],
            ],
            [
                'network_code' => 'arbitrum_sepolia',
                'currency_code' => 'ETH',
                'decimals' => 18,
                'contract_address' => null,
                'min_confirmations' => 3,
                'min_withdrawal_amount' => '0.000000000000000001',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'sort_order' => 0,
                'metadata' => ['provider' => 'native', 'is_testnet' => true],
            ],
            [
                'network_code' => 'base_sepolia',
                'currency_code' => 'ETH',
                'decimals' => 18,
                'contract_address' => null,
                'min_confirmations' => 3,
                'min_withdrawal_amount' => '0.000000000000000001',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'sort_order' => 0,
                'metadata' => ['provider' => 'native', 'is_testnet' => true],
            ],
            [
                'network_code' => 'polygon_amoy',
                'currency_code' => 'MATIC',
                'decimals' => 18,
                'contract_address' => null,
                'min_confirmations' => 3,
                'min_withdrawal_amount' => '0.000000000000000001',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'sort_order' => 0,
                'metadata' => ['provider' => 'native', 'is_testnet' => true],
            ],
            [
                'network_code' => 'tron_nile',
                'currency_code' => 'TRX',
                'decimals' => 6,
                'contract_address' => null,
                'min_confirmations' => 3,
                'min_withdrawal_amount' => '0.000001',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'sort_order' => 0,
                'metadata' => ['provider' => 'native', 'is_testnet' => true],
            ],
            [
                'network_code' => 'tron_nile',
                'currency_code' => 'USDT',
                'decimals' => 6,
                'contract_address' => env('NILE_USDT_CONTRACT'),
                'min_confirmations' => 3,
                'min_withdrawal_amount' => '1.000000',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'sort_order' => 1,
                'metadata' => ['provider' => 'token', 'is_testnet' => true],
            ],
            [
                'network_code' => 'bitcoin_testnet',
                'currency_code' => 'BTC',
                'decimals' => 8,
                'contract_address' => null,
                'min_confirmations' => 2,
                'min_withdrawal_amount' => '0.00000001',
                'is_active' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_deposit_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'is_withdrawal_enabled' => env('BLOCKCHAIN_TESTNET_MODE', false),
                'sort_order' => 0,
                'metadata' => ['provider' => 'native', 'is_testnet' => true],
            ],
        ];

        foreach ($rows as $row) {
            $networkId = $networkIds[$row['network_code']] ?? null;
            $currencyId = $currencyIds[$row['currency_code']] ?? null;

            if (!$networkId) {
                throw new \RuntimeException("Network not found for code [{$row['network_code']}]. Run NetworksSeeder first.");
            }

            if (!$currencyId) {
                throw new \RuntimeException("Currency not found for code [{$row['currency_code']}].");
            }

            // Token pairs can be skipped if contract is not configured.
            if ($row['contract_address'] === null && $row['currency_code'] !== $this->nativeCurrencyFor($row['network_code'])) {
                continue;
            }

            if ($row['contract_address'] === null && in_array($row['currency_code'], ['USDT', 'USDC'], true)) {
                // token row without contract is useless; skip gracefully
                continue;
            }

            DB::table('currency_networks')->updateOrInsert(
                [
                    'network_id' => $networkId,
                    'currency_id' => $currencyId,
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
                    'is_active' => $row['is_active'],
                    'is_deposit_enabled' => $row['is_deposit_enabled'],
                    'is_withdrawal_enabled' => $row['is_withdrawal_enabled'],
                    'sort_order' => $row['sort_order'],
                    'metadata' => json_encode($row['metadata'], JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    /**
     * @return array<string,int>
     */
    private function loadNetworkIds(): array
    {
        $codes = [
            'ethereum',
            'arbitrum',
            'base',
            'polygon',
            'bsc',
            'tron',
            'bitcoin',
            'ethereum_sepolia',
            'arbitrum_sepolia',
            'base_sepolia',
            'polygon_amoy',
            'tron_nile',
            'bitcoin_testnet',
        ];

        $ids = [];
        foreach ($codes as $code) {
            $id = DB::table('networks')->where('code', $code)->value('id');
            if (!$id) {
                throw new \RuntimeException("Network [{$code}] is missing. Run NetworksSeeder first.");
            }
            $ids[$code] = (int) $id;
        }

        return $ids;
    }

    /**
     * @return array<string,int>
     */
    private function loadCurrencyIds(): array
    {
        $codes = ['ETH', 'TRX', 'BTC', 'USDT', 'USDC', 'MATIC', 'BNB'];

        $ids = [];
        foreach ($codes as $code) {
            $id = DB::table('currencies')->where('code', $code)->value('id');
            if (!$id) {
                throw new \RuntimeException("Currency [{$code}] is missing. Add it to currencies seeder before running this seeder.");
            }
            $ids[$code] = (int) $id;
        }

        return $ids;
    }

    private function nativeCurrencyFor(string $networkCode): string
    {
        return match ($networkCode) {
            'ethereum', 'arbitrum', 'base', 'ethereum_sepolia', 'arbitrum_sepolia', 'base_sepolia' => 'ETH',
            'polygon', 'polygon_amoy' => 'MATIC',
            'bsc' => 'BNB',
            'tron', 'tron_nile' => 'TRX',
            'bitcoin', 'bitcoin_testnet' => 'BTC',
            default => throw new \InvalidArgumentException("Unknown network code [{$networkCode}]."),
        };
    }
}
