<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class FeeRulesSeeder extends Seeder
{
    public function run(): void
    {
        $pairs = DB::table('currency_networks')->get()->keyBy('id');

        foreach ($pairs as $pair) {
            $currencyCode = DB::table('currencies')->where('id', $pair->currency_id)->value('code');
            $networkCode = DB::table('networks')->where('id', $pair->network_id)->value('code');

            $fee = match ("{$networkCode}:{$currencyCode}") {
                'ethereum:ETH' => '0.002000000000000000',
                'ethereum:USDT' => '5.000000',
                'tron:TRX' => '1.000000',
                'tron:USDT' => '1.000000',
                'bitcoin:BTC' => '0.00020000',
                default => '0',
            };

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
}
