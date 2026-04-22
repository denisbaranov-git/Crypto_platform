<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ConfirmationRulesSeeder extends Seeder
{
    public function run(): void
    {
        $pairs = DB::table('currency_networks')->get()->keyBy('id');

        foreach ($pairs as $pair) {
            $currencyCode = DB::table('currencies')->where('id', $pair->currency_id)->value('code');
            $networkCode = DB::table('networks')->where('id', $pair->network_id)->value('code');

            $confirmations = match ("{$networkCode}:{$currencyCode}") {
                'ethereum:ETH' => 12,
                'ethereum:USDT' => 12,
                'tron:TRX' => 20,
                'tron:USDT' => 20,
                'bitcoin:BTC' => 6,
                default => 12,
            };

            DB::table('confirmation_rules')->updateOrInsert(
                [
                    'currency_network_id' => $pair->id,
                    'amount_threshold' => null,
                    'confirmation_type' => 'blocks',
                    'priority' => 0,
                ],
                [
                    'confirmations_required' => $confirmations,
                    'description' => "{$networkCode} / {$currencyCode} default confirmation rule",
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
