<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class FeeRulesSeeder extends Seeder
{
    public function run(): void
    {
        $currencyNetworks = DB::table('currency_networks')->get();

        $networkCodes = DB::table('networks')->pluck('code', 'id')->all();
        $currencyCodes = DB::table('currencies')->pluck('code', 'id')->all();

        foreach ($currencyNetworks as $pair) {
            $networkCode = $networkCodes[$pair->network_id] ?? null;
            $currencyCode = $currencyCodes[$pair->currency_id] ?? null;

            if (!$networkCode || !$currencyCode) {
                continue;
            }

            $isTestnet = $this->isTestnetNetwork($networkCode);

            $fee = $this->resolveFee($networkCode, $currencyCode, $isTestnet);

            DB::table('fee_rules')->updateOrInsert(
                [
                    'currency_network_id' => $pair->id,
                    'min_amount' => null,
                    'max_amount' => null,
                    'priority' => 0,
                ],
                [
                    'fee' => $fee,
                    'fee_type' => 'fixed',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function resolveFee(string $networkCode, string $currencyCode, bool $isTestnet): string
    {
        // CHANGE: testnet fees kept zero to avoid accidental charging in dev/test environments.
        if ($isTestnet) {
            return '0';
        }

        // Bitcoin mainnet
        if ($currencyCode === 'BTC') {
            return '0.00020000';
        }

        // Tron
        if ($networkCode === 'tron' || $networkCode === 'tron_nile') {
            return match ($currencyCode) {
                'TRX' => '1.000000',
                'USDT' => '1.000000',
                default => '0',
            };
        }

        // EVM family
        if ($this->isEvmNetwork($networkCode)) {
            return match ($currencyCode) {
                'ETH', 'MATIC', 'BNB' => '0.002000000000000000',
                'USDT', 'USDC' => '5.000000',
                default => '0',
            };
        }

        return '0';
    }

    private function isEvmNetwork(string $networkCode): bool
    {
        return in_array($networkCode, [
            'ethereum',
            'arbitrum',
            'base',
            'polygon',
            'bsc',
            'ethereum_sepolia',
            'arbitrum_sepolia',
            'base_sepolia',
            'polygon_amoy',
        ], true);
    }

    private function isTestnetNetwork(string $networkCode): bool
    {
        return str_contains($networkCode, 'testnet')
            || str_contains($networkCode, 'sepolia')
            || str_contains($networkCode, 'nile')
            || str_contains($networkCode, 'amoy');
    }
}
