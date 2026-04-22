<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class CurrenciesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'BTC', 'name' => 'Bitcoin', 'type' => 'crypto', 'symbol' => '₿'],
            ['code' => 'ETH', 'name' => 'Ethereum', 'type' => 'crypto', 'symbol' => 'Ξ'],
            ['code' => 'TRX', 'name' => 'Tron', 'type' => 'crypto', 'symbol' => 'TRX'],
            ['code' => 'USDT', 'name' => 'Tether USD', 'type' => 'crypto', 'symbol' => '₮'],
            ['code' => 'USD', 'name' => 'US Dollar', 'type' => 'fiat', 'symbol' => '$'],
        ];

        foreach ($rows as $row) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'symbol' => $row['symbol'],
                    'logo_url' => null,
                    'sort_order' => 0,
                    'is_active' => true,
                    'metadata' => json_encode([], JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
