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
            // === Mainnet Hot Wallets ===
            [
                'network_code' => 'ethereum',
                'address' => env('SYSTEM_WALLET_ETHEREUM_HOT_ADDRESS'),
                'private_key' => env('SYSTEM_WALLET_ETHEREUM_HOT_PRIVATE_KEY'),
                'encrypted_key' => env('SYSTEM_WALLET_ETHEREUM_HOT_PRIVATE_KEY_ENCRYPTED'),
                'type' => 'hot',
            ],
            [
                'network_code' => 'tron',
                'address' => env('SYSTEM_WALLET_TRON_HOT_ADDRESS'),
                'private_key' => env('SYSTEM_WALLET_TRON_HOT_PRIVATE_KEY'),
                'encrypted_key' => env('SYSTEM_WALLET_TRON_HOT_PRIVATE_KEY_ENCRYPTED'),
                'type' => 'hot',
            ],
            [
                'network_code' => 'bitcoin',
                'address' => env('SYSTEM_WALLET_BITCOIN_HOT_ADDRESS'),
                'private_key' => env('SYSTEM_WALLET_BITCOIN_HOT_PRIVATE_KEY'),
                'encrypted_key' => env('SYSTEM_WALLET_BITCOIN_HOT_PRIVATE_KEY_ENCRYPTED'),
                'type' => 'hot',
            ],

            // === Testnet Hot Wallets ===
            [
                'network_code' => 'ethereum_sepolia',
                'address' => env('SYSTEM_WALLET_ETHEREUM_SEPOLIA_HOT_ADDRESS'),
                'private_key' => env('SYSTEM_WALLET_ETHEREUM_SEPOLIA_HOT_PRIVATE_KEY'),
                'encrypted_key' => env('SYSTEM_WALLET_ETHEREUM_SEPOLIA_HOT_PRIVATE_KEY_ENCRYPTED'),
                'type' => 'hot',
            ],
            [
                'network_code' => 'tron_nile',
                'address' => env('SYSTEM_WALLET_TRON_NILE_HOT_ADDRESS'),
                'private_key' => env('SYSTEM_WALLET_TRON_NILE_HOT_PRIVATE_KEY'),
                'encrypted_key' => env('SYSTEM_WALLET_TRON_NILE_HOT_PRIVATE_KEY_ENCRYPTED'),
                'type' => 'hot',
            ],
            [
                'network_code' => 'bitcoin_testnet',
                'address' => env('SYSTEM_WALLET_BITCOIN_TESTNET_HOT_ADDRESS'),
                'private_key' => env('SYSTEM_WALLET_BITCOIN_TESTNET_HOT_PRIVATE_KEY'),
                'encrypted_key' => env('SYSTEM_WALLET_BITCOIN_TESTNET_HOT_PRIVATE_KEY_ENCRYPTED'),
                'type' => 'hot',
            ],

            // === Mainnet Cold Wallets ===
            [
                'network_code' => 'ethereum',
                'address' => env('SYSTEM_WALLET_ETHEREUM_COLD_ADDRESS'),
                'private_key' => env('SYSTEM_WALLET_ETHEREUM_COLD_PRIVATE_KEY'),
                'encrypted_key' => env('SYSTEM_WALLET_ETHEREUM_COLD_PRIVATE_KEY_ENCRYPTED'),
                'type' => 'cold',
            ],
            [
                'network_code' => 'tron',
                'address' => env('SYSTEM_WALLET_TRON_COLD_ADDRESS'),
                'private_key' => env('SYSTEM_WALLET_TRON_COLD_PRIVATE_KEY'),
                'encrypted_key' => env('SYSTEM_WALLET_TRON_COLD_PRIVATE_KEY_ENCRYPTED'),
                'type' => 'cold',
            ],
            [
                'network_code' => 'bitcoin',
                'address' => env('SYSTEM_WALLET_BITCOIN_COLD_ADDRESS'),
                'private_key' => env('SYSTEM_WALLET_BITCOIN_COLD_PRIVATE_KEY'),
                'encrypted_key' => env('SYSTEM_WALLET_BITCOIN_COLD_PRIVATE_KEY_ENCRYPTED'),
                'type' => 'cold',
            ],
        ];

        foreach ($rows as $row) {
            $networkId = DB::table('networks')
                ->where('code', $row['network_code'])
                ->value('id');

            // Если сеть не найдена в БД
            if (!$networkId) {
                if (app()->environment('production')) {
                    throw new RuntimeException(
                        "Network not found: {$row['network_code']}"
                    );
                }

                $this->command?->warn(
                    "Skipping wallet for network '{$row['network_code']}' - network not found"
                );
                continue;
            }

            $hasAddress = !empty($row['address']);
            $hasEncryptedKey = !empty($row['encrypted_key']);
            $hasRawKey = !empty($row['private_key']);

            // В production обязательно наличие адреса и хотя бы одного ключа
            if (app()->environment('production')) {
                if (!$hasAddress) {
                    throw new RuntimeException(
                        "Missing address for system wallet: {$row['network_code']} ({$row['type']})"
                    );
                }

                if (!$hasEncryptedKey && !$hasRawKey) {
                    throw new RuntimeException(
                        "Missing private key for system wallet: {$row['network_code']} ({$row['type']})"
                    );
                }
            }

            // Пропускаем если вообще нет данных
            if (!$hasAddress) {
                continue;
            }

            // Определяем encrypted_private_key
            if ($hasEncryptedKey) {
                $encryptedKey = $row['encrypted_key'];
            } elseif ($hasRawKey) {
                $encryptedKey = encrypt($row['private_key']);
            } else {
                // В dev окружении допускается пустой ключ
                if (app()->environment('production')) {
                    throw new RuntimeException(
                        "Cannot encrypt private key for: {$row['network_code']} ({$row['type']})"
                    );
                }
                $encryptedKey = '';
            }

            // Используем updateOrInsert с уникальностью [network_id, address]
            DB::table('system_wallets')->updateOrInsert(
                [
                    'network_id' => $networkId,
                    'address' => $row['address'],  // Уникальность по [network_id, address]
                ],
                [
                    'type' => $row['type'],
                    'encrypted_private_key' => $encryptedKey,
                    'next_nonce' => 0,
                    'current_nonce' => 0,
                    'nonce_synced_at' => null,
                    'status' => 'active',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $this->command?->info(
                "✓ System wallet seeded: {$row['network_code']} ({$row['type']}) - {$row['address']}"
            );
        }
    }
}
