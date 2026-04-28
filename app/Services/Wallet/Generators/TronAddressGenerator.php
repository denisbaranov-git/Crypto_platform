<?php

declare(strict_types=1);

namespace App\Services\Wallet\Generators;


use App\Services\Wallet\AddressGeneratorInterface;
use App\Services\Wallet\Traits\Base58CheckTrait;
use Elliptic\EC;
use kornrunner\Keccak;

class TronAddressGenerator
{
    use Base58CheckTrait;

    public function generate(): array
    {
        // 1. Генерируем ключевую пару (та же кривая secp256k1)
        $ec = new EC('secp256k1');
        $keyPair = $ec->genKeyPair();

        // 2. Приватный ключ (64 hex символа)
        $privateKey = $keyPair->getPrivate()->toString(16, 64);

        // 3. Публичный ключ (не сжатый, с префиксом 04)
        $publicKey = $keyPair->getPublic()->encode('hex', false);

        // 4. Убираем префикс 04
        $publicKeyWithoutPrefix = substr($publicKey, 2);

        // 5. Keccak-256 хеш от бинарных данных публичного ключа
        $binaryPublicKey = hex2bin($publicKeyWithoutPrefix);
        $hash = Keccak::hash($binaryPublicKey, 256);

        // 6. Tron добавляет байт 0x41 перед адресом (версия)
        $hexWithPrefix = '41' . substr($hash, -40);

        // 7. Base58Check кодирование (используем общий trait)
        $address = $this->base58checkEncode($hexWithPrefix);

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
        $hexWithPrefix = '41' . substr($hash, -40);

        return $this->base58checkEncode($hexWithPrefix);
    }
}
