<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Services\Wallet\Generators\EvmHDAddressGenerator;
use App\Services\Wallet\Generators\TronHDAddressGenerator;
use App\Services\Wallet\Generators\BitcoinHDAddressGenerator;

class HDAddressGenerator
{
    /**
     * Генерирует адрес из HD-кошелька по индексу
     *
     * @param string $network Код сети
     * @param int $index Индекс адреса
     * @return array|string Адрес (для Bitcoin возвращается полная информация)
     */
    public function generate(string $network, int $index): array|string
    {
        $xpub = config("wallet.{$network}_xpub");

        if (!$xpub) {
            throw new \InvalidArgumentException(
                "XPUB not configured for network: {$network}"
            );
        }

        return match ($network) {
            // EVM-совместимые сети - возвращают строку (адрес)
            'ethereum',
            'ethereum_sepolia',
            'arbitrum_sepolia',
            'base_sepolia',
            'polygon_amoy' => (new EvmHDAddressGenerator($xpub))->generate($index),

            // TRON - возвращает строку (адрес)
            'tron',
            'tron_nile' => (new TronHDAddressGenerator($xpub))->generate($index),

            // Bitcoin - возвращает массив с полной информацией
            'bitcoin' => (new BitcoinHDAddressGenerator($xpub))->generate($index, false),
            'bitcoin_testnet' => (new BitcoinHDAddressGenerator($xpub))->generate($index, true),

            default => throw new \InvalidArgumentException(
                "Unsupported network: {$network}"
            ),
        };
    }

    /**
     * Генерирует несколько адресов для заданной сети
     *
     * @param string $network Код сети
     * @param int $startIndex Начальный индекс
     * @param int $count Количество адресов
     * @return array Массив адресов
     */
    public function generateMultiple(string $network, int $startIndex, int $count): array
    {
        $addresses = [];

        for ($i = 0; $i < $count; $i++) {
            $addresses[] = $this->generate($network, $startIndex + $i);
        }

        return $addresses;
    }
}
