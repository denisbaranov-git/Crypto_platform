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
            // Криптовалюты
            ['code' => 'BTC', 'name' => 'Bitcoin', 'type' => 'crypto', 'symbol' => '₿'],
            ['code' => 'ETH', 'name' => 'Ethereum', 'type' => 'crypto', 'symbol' => 'Ξ'],
            ['code' => 'TRX', 'name' => 'Tron', 'type' => 'crypto', 'symbol' => 'TRX'],
            ['code' => 'MATIC', 'name' => 'Polygon', 'type' => 'crypto', 'symbol' => 'MATIC'],

            // Стейблкоины
            ['code' => 'USDT', 'name' => 'Tether USD', 'type' => 'crypto', 'symbol' => '₮', 'is_stablecoin' => true],
            ['code' => 'USDC', 'name' => 'USD Coin', 'type' => 'crypto', 'symbol' => 'USDC', 'is_stablecoin' => true],
            ['code' => 'DAI', 'name' => 'Dai Stablecoin', 'type' => 'crypto', 'symbol' => 'DAI', 'is_stablecoin' => true],

            // Фиатные валюты
            ['code' => 'USD', 'name' => 'US Dollar', 'type' => 'fiat', 'symbol' => '$'],
            ['code' => 'EUR', 'name' => 'Euro', 'type' => 'fiat', 'symbol' => '€'],
            ['code' => 'RUB', 'name' => 'Russian Ruble', 'type' => 'fiat', 'symbol' => '₽'],
        ];

        foreach ($rows as $row) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'symbol' => $row['symbol'],
                    'logo_url' => $this->getDefaultLogo($row['code']),
                    'sort_order' => $this->getSortOrder($row['type'], $row['code']),
                    'is_active' => true,
                    'metadata' => json_encode([
                        'is_stablecoin' => $row['is_stablecoin'] ?? false,
                        'coingecko_id' => $this->getCoingeckoId($row['code']),
                    ], JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function getSortOrder(string $type, string $code): int
    {
        // Фиатные валюты в начале
        if ($type === 'fiat') {
            return match($code) {
                'USD' => 1,
                'EUR' => 2,
                'RUB' => 3,
                default => 100,
            };
        }

        // Популярные криптовалюты
        return match($code) {
            'BTC' => 10,
            'ETH' => 11,
            'USDT' => 12,
            'USDC' => 13,
            'DAI' => 14,
            'TRX' => 15,
            'MATIC' => 16,
            default => 50,
        };
    }

    private function getDefaultLogo(string $code): ?string
    {
        // Заглушки для логотипов (можно заменить на реальные URL)
        return match($code) {
            'BTC' => '/images/crypto/btc.svg',
            'ETH' => '/images/crypto/eth.svg',
            'TRX' => '/images/crypto/trx.svg',
            'MATIC' => '/images/crypto/matic.svg',
            'USDT' => '/images/crypto/usdt.svg',
            'USDC' => '/images/crypto/usdc.svg',
            'DAI' => '/images/crypto/dai.svg',
            default => null,
        };
    }

    private function getCoingeckoId(string $code): ?string
    {
        // ID для API CoinGecko (если нужны курсы валют)
        return match($code) {
            'BTC' => 'bitcoin',
            'ETH' => 'ethereum',
            'TRX' => 'tron',
            'MATIC' => 'matic-network',
            'USDT' => 'tether',
            'USDC' => 'usd-coin',
            'DAI' => 'dai',
            default => null,
        };
    }
}
