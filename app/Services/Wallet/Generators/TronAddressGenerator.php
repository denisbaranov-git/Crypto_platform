<?php

namespace App\Services\Wallet\Generators;

use Elliptic\EC;
use kornrunner\Keccak;
use StephenHill\Base58;

class TronAddressGenerator
{
    public function generate(): array
    {
        // 1. Генерируем ключевую пару (та же кривая secp256k1)
        $ec = new EC('secp256k1');
        $keyPair = $ec->genKeyPair();

        // 2. Приватный ключ (64 hex символа) - такой же как в Ethereum!
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

        // 7. Base58Check кодирование (двойной SHA256 для контрольной суммы)
        $address = $this->base58check($hexWithPrefix);

        return [
            'public_key' => $publicKey,
            'private_key' => $privateKey,
            'address'     => $address,
        ];
    }

    /**
     * Base58Check кодирование для Tron адресов
     *
     * @param string $hex Адрес с префиксом 41 в hex (например, "41" + 40 hex символов)
     * @return string Base58Check закодированный адрес (начинается с T)
     */

    protected function base58check(string $hex): string // убрать в Trait
    {
        // 1. Конвертируем hex в бинарные данные
        $bin = hex2bin($hex);

        // 2. Двойной SHA256 для контрольной суммы
        $hash1 = hash('sha256', $bin, true);
        $hash2 = hash('sha256', $hash1, true);

        // 3. Первые 4 байта - контрольная сумма
        $checksum = substr($hash2, 0, 4);

        // 4. Добавляем контрольную сумму к исходным данным
        $binWithChecksum = $bin . $checksum;

        // 5. Base58 кодирование
        $base58 = new Base58();
        return $base58->encode($binWithChecksum);
    }
}
