<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Domain\Wallet\Services\GeneratedAddress;
use App\Domain\Wallet\Services\HDAddressGeneratorInterface;
use App\Services\Wallet\Crypto\Contracts\Bip32KeyServiceInterface;
use App\Services\Wallet\Generators\BitcoinHDAddressGenerator;
use App\Services\Wallet\Generators\EvmHDAddressGenerator;
use App\Services\Wallet\Generators\TronHDAddressGenerator;
use InvalidArgumentException;

final class HDAddressGenerator implements HDAddressGeneratorInterface
{
    public function __construct(
        private readonly Bip32KeyServiceInterface $bip32,
    ) {
    }

    public function generate(string $network, int $index): GeneratedAddress
    {
        $xpub = config("wallet.{$network}_xpub");

        if (!$xpub) {
            throw new InvalidArgumentException("XPUB not configured for network: {$network}");
        }
        /**
         * ethereum
         * arbitrum
         * base
         * polygon
         * bsc
         * tron
         * bitcoin
         * ethereum_sepolia
         * arbitrum_sepolia
         * base_sepolia
         * polygon_amoy
         * tron_nile
         * bitcoin_testnet
         */
        return match ($network) {
            'ethereum',
            'ethereum_sepolia',
            'arbitrum_sepolia',
            'base_sepolia',
            'polygon_amoy' => (new EvmHDAddressGenerator($this->bip32, $xpub, $network))->generate($index),

            'tron',
            'tron_nile' => (new TronHDAddressGenerator($this->bip32, $xpub, $network))->generate($index),

            'bitcoin' => (new BitcoinHDAddressGenerator($this->bip32, $xpub))->generate($index, false, 'segwit', 0),
            'bitcoin_testnet' => (new BitcoinHDAddressGenerator($this->bip32, $xpub))->generate($index, true, 'segwit', 0),

            default => throw new InvalidArgumentException("Unsupported network: {$network}"),
        };
    }

    public function generateMultiple(string $network, int $startIndex, int $count): array
    {
        $addresses = [];

        for ($i = 0; $i < $count; $i++) {
            $addresses[] = $this->generate($network, $startIndex + $i);
        }

        return $addresses;
    }
}
