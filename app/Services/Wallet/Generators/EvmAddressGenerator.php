<?php

declare(strict_types=1);

namespace App\Services\Wallet\Generators;

use Elliptic\EC;
use kornrunner\Keccak;

/**
 * Генератор адресов для всех EVM-совместимых сетей
 * (Ethereum, Ethereum Sepolia, Arbitrum Sepolia, Base Sepolia, Polygon Amoy,
 * BSC, Avalanche, Optimism и любые другие EVM-сети)
 */
class EvmAddressGenerator
{
    public function generate(): array
    {
        // 1. Генерируем ключевую пару на кривой secp256k1
        $ec = new EC('secp256k1');
        $keyPair = $ec->genKeyPair();

        // 2. Приватный ключ (64 hex символа)
        $privateKey = $keyPair->getPrivate()->toString(16, 64);

        // 3. Публичный ключ (не сжатый, с префиксом 04)
        $publicKey = $keyPair->getPublic()->encode('hex', false);

        // 4. Убираем первый байт (04) для хеширования
        $publicKeyWithoutPrefix = substr($publicKey, 2);

        // 5. Конвертируем hex в бинарные данные для Keccak
        $binaryPublicKey = hex2bin($publicKeyWithoutPrefix);

        // 6. Keccak-256 хеш от бинарных данных публичного ключа
        $hash = Keccak::hash($binaryPublicKey, 256);

        // 7. Адрес = последние 20 байт (40 hex символов) хеша с префиксом 0x
        $address = '0x' . substr($hash, -40);

        return [
            'public_key' => $publicKey,
            'private_key' => $privateKey,
            'address'     => $address,
        ];
    }

    /**
     * Получить адрес из приватного ключа
     */
    public function addressFromPrivateKey(string $privateKey): string
    {
        $ec = new EC('secp256k1');
        $keyPair = $ec->keyFromPrivate($privateKey);
        $publicKey = $keyPair->getPublic()->encode('hex', false);
        $publicKeyWithoutPrefix = substr($publicKey, 2);
        $binaryPublicKey = hex2bin($publicKeyWithoutPrefix);
        $hash = Keccak::hash($binaryPublicKey, 256);

        return '0x' . substr($hash, -40);
    }

    /**
     * Проверить, является ли это EVM-совместимой сетью
     */
    public static function isEvmNetwork(string $network): bool
    {
        $evmNetworks = [
            'ethereum',
            'ethereum_sepolia',
            'arbitrum',
            'arbitrum_sepolia',
            'base',
            'base_sepolia',
            'polygon',
            'polygon_amoy',
            'bsc',
            'bsc_testnet',
            'optimism',
            'optimism_sepolia',
            'avalanche',
            'avalanche_fuji',
            'fantom',
            'fantom_testnet',
        ];

        return in_array($network, $evmNetworks);
    }
}
