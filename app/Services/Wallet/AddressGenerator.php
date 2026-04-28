<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Services\Wallet\Generators\BitcoinAddressGenerator;
use App\Services\Wallet\Generators\EvmAddressGenerator;
use App\Services\Wallet\Generators\TronAddressGenerator;

class AddressGenerator implements AddressGeneratorInterface
{
    public function generate(string $network): array
    {
        return match ($network) {
            // EVM-совместимые сети (все используют одинаковый генератор)
            'ethereum',
            'ethereum_sepolia',
            'arbitrum_sepolia',
            'base_sepolia',
            'polygon_amoy' => (new EvmAddressGenerator())->generate(),

            // TRON и его тестовая сеть
            'tron',
            'tron_nile' => (new TronAddressGenerator())->generate(),

            // Bitcoin
            'bitcoin' => (new BitcoinAddressGenerator())->generate(['testnet' => false,]),

            'bitcoin_testnet' => (new BitcoinAddressGenerator())->generate(['testnet' => true,]),

            default => throw new \Exception("Unsupported network: {$network}"),
        };
    }

    /**
     * Проверить, поддерживается ли сеть
     */
    public function supports(string $network): bool
    {
        $supportedNetworks = [
            'ethereum', 'ethereum_sepolia',
            'arbitrum_sepolia', 'base_sepolia', 'polygon_amoy',
            'tron', 'tron_nile',
            'bitcoin', 'bitcoin_testnet',
        ];

        return in_array($network, $supportedNetworks);
    }

    /**
     * Получить список поддерживаемых сетей
     */
    public function getSupportedNetworks(): array
    {
        return [
            'evm' => ['ethereum', 'ethereum_sepolia', 'arbitrum_sepolia', 'base_sepolia', 'polygon_amoy'],
            'tron' => ['tron', 'tron_nile'],
            'bitcoin' => ['bitcoin', 'bitcoin_testnet'],
        ];
    }
}
