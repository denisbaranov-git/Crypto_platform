<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencyNetworkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Сети
        $networks = [
            ['name' => 'Ethereum', 'code' => 'ethereum', 'chain_id' => 1, 'native_currency_code' => 'ETH'],
            ['name' => 'BNB Chain', 'code' => 'bsc', 'chain_id' => 56, 'native_currency_code' => 'BNB'],
            ['name' => 'Tron', 'code' => 'tron', 'chain_id' => null, 'native_currency_code' => 'TRX'],
            ['name' => 'Polygon', 'code' => 'polygon', 'chain_id' => 137, 'native_currency_code' => 'MATIC'],
        ];

        foreach ($networks as $network) {
            DB::table('networks')->insert($network);
        }

        // Валюты
        $currencies = [
            ['code' => 'USDT', 'name' => 'Tether USD', 'type' => 'crypto'],
            ['code' => 'ETH', 'name' => 'Ethereum', 'type' => 'crypto'],
            ['code' => 'BNB', 'name' => 'BNB', 'type' => 'crypto'],
            ['code' => 'TRX', 'name' => 'Tron', 'type' => 'crypto'],
            ['code' => 'USDC', 'name' => 'USD Coin', 'type' => 'crypto'],
        ];

        foreach ($currencies as $currency) {
            DB::table('currencies')->insert($currency);
        }

        // Связи сетей и валют (currency_network)
        $currencyNetworks = [
            // Ethereum
            ['network_id' => 1, 'currency_id' => 1, 'decimals' => 6, 'contract_address' => '0xdAC17F958D2ee523a2206206994597C13D831ec7', 'min_confirmations' => 12],
            ['network_id' => 1, 'currency_id' => 2, 'decimals' => 18, 'contract_address' => null, 'min_confirmations' => 12], // ETH native
            ['network_id' => 1, 'currency_id' => 5, 'decimals' => 6, 'contract_address' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', 'min_confirmations' => 12],

            // BSC
            ['network_id' => 2, 'currency_id' => 1, 'decimals' => 18, 'contract_address' => '0x55d398326f99059ff775485246999027b3197955', 'min_confirmations' => 15],
            ['network_id' => 2, 'currency_id' => 3, 'decimals' => 18, 'contract_address' => null, 'min_confirmations' => 15], // BNB native
            ['network_id' => 2, 'currency_id' => 5, 'decimals' => 18, 'contract_address' => '0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d', 'min_confirmations' => 15],

            // Tron
            ['network_id' => 3, 'currency_id' => 1, 'decimals' => 6, 'contract_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 'min_confirmations' => 19],
            ['network_id' => 3, 'currency_id' => 4, 'decimals' => 6, 'contract_address' => null, 'min_confirmations' => 19], // TRX native
        ];

        foreach ($currencyNetworks as $item) {
            DB::table('currency_networks')->insert($item);
        }
    }
}
