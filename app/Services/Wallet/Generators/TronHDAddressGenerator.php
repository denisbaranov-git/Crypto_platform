<?php

declare(strict_types=1);

namespace App\Services\Wallet\Generators;

use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use kornrunner\Keccak;
use StephenHill\Base58;

class TronHDAddressGenerator
{
    use \App\Services\Wallet\Traits\Base58CheckTrait;

    private string $xpub;

    public function __construct(string $xpub)
    {
        $this->xpub = $xpub;
    }

    /**
     * Генерирует адрес TRON из HD-кошелька
     * Использует путь: m/44'/195'/0'/0/$index
     *
     * @param int $index Индекс адреса
     * @return string Адрес TRON (начинается с T)
     */
    public function generate(int $index): string
    {
        // Восстанавливаем ключ из xpub
        $extendedKey = HierarchicalKeyFactory::fromExtended($this->xpub);

        // Деривируем дочерний ключ по индексу
        $childKey = $extendedKey->deriveChild($index);

        // Получаем публичный ключ
        $publicKey = $childKey->getPublicKey()->getHex();

        // Убираем префикс 04
        $publicKeyBin = hex2bin($publicKey);
        $publicKeyWithoutPrefix = substr($publicKeyBin, 1);

        // Keccak-256 хеш
        $hash = Keccak::hash($publicKeyWithoutPrefix, 256);

        // Tron добавляет байт 0x41 (версия адреса)
        $hexWithPrefix = '41' . substr($hash, -40);

        // Base58Check кодирование
        return $this->base58checkEncode($hexWithPrefix);
    }
}
