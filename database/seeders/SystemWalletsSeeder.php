<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SystemWalletsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'network_code' => 'ethereum',
                'address' => env('SYSTEM_WALLET_ETHEREUM_HOT_ADDRESS'),
                'private_key' => env('SYSTEM_WALLET_ETHEREUM_HOT_PRIVATE_KEY'),
                'type' => 'hot',
            ],
            [
                'network_code' => 'tron',
                'address' => env('SYSTEM_WALLET_TRON_HOT_ADDRESS'),
                'private_key' => env('SYSTEM_WALLET_TRON_HOT_PRIVATE_KEY'),
                'type' => 'hot',
            ],
            [
                'network_code' => 'bitcoin',
                'address' => env('SYSTEM_WALLET_BITCOIN_HOT_ADDRESS'),
                'private_key' => env('SYSTEM_WALLET_BITCOIN_HOT_PRIVATE_KEY'),
                'type' => 'hot',
            ],
        ];

        foreach ($rows as $row) {
            if (empty($row['address']) || empty($row['private_key'])) {
                if (app()->environment('production')) {
                    throw new RuntimeException("Missing system wallet secrets for [{$row['network_code']}].");
                }

                continue;
            }

            $networkId = DB::table('networks')->where('code', $row['network_code'])->value('id');

            DB::table('system_wallets')->updateOrInsert(
                [
                    'network_id' => $networkId,
                    'type' => $row['type'],
                ],
                [
                    'address' => $row['address'],
                    'encrypted_private_key' => encrypt($row['private_key']),
                    'status' => 'active',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
