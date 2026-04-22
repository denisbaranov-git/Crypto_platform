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
        $ethereumId = DB::table('networks')->where('code', 'ethereum')->value('id');
        $tronId = DB::table('networks')->where('code', 'tron')->value('id');
        $bitcoinId = DB::table('networks')->where('code', 'bitcoin')->value('id');

        $ethId = DB::table('currencies')->where('code', 'ETH')->value('id');
        $trxId = DB::table('currencies')->where('code', 'TRX')->value('id');
        $btcId = DB::table('currencies')->where('code', 'BTC')->value('id');
        $usdtId = DB::table('currencies')->where('code', 'USDT')->value('id');

        foreach ([
                     ['network_id' => $ethereumId, 'currency_id' => $ethId, 'decimals' => 18, 'contract_address' => null, 'min_confirmations' => 12, 'min_withdrawal_amount' => '0.000000000000000001'],
                     ['network_id' => $ethereumId, 'currency_id' => $usdtId, 'decimals' => 6, 'contract_address' => env('ETHEREUM_USDT_CONTRACT'), 'min_confirmations' => 12, 'min_withdrawal_amount' => '1.000000'],
                     ['network_id' => $tronId, 'currency_id' => $trxId, 'decimals' => 6, 'contract_address' => null, 'min_confirmations' => 20, 'min_withdrawal_amount' => '0.000001'],
                     ['network_id' => $tronId, 'currency_id' => $usdtId, 'decimals' => 6, 'contract_address' => env('TRON_USDT_CONTRACT'), 'min_confirmations' => 20, 'min_withdrawal_amount' => '1.000000'],
                     ['network_id' => $bitcoinId, 'currency_id' => $btcId, 'decimals' => 8, 'contract_address' => null, 'min_confirmations' => 6, 'min_withdrawal_amount' => '0.00000001'],
                 ] as $row) {
            if (! $row['network_id'] || ! $row['currency_id']) {
                throw new RuntimeException('Missing network/currency dependency while seeding currency_networks.');
            }

            if ($row['currency_id'] === $usdtId && empty($row['contract_address'])) {
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
                    'is_active' => true,
                    'is_deposit_enabled' => true,
                    'is_withdrawal_enabled' => true,
                    'sort_order' => 0,
                    'metadata' => json_encode([], JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
