<?php

namespace App\Services\Wallet\Generators;

use Elliptic\EC;
use kornrunner\Keccak;

class EthereumAddressGenerator
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
}
