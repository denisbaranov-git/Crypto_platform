<?php

namespace App\Services\Wallet\Generators;

//use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use kornrunner\Keccak;
use StephenHill\Base58;


class TronHDAddressGenerator
{
    private string $xpub;
    private HierarchicalKeyFactory $factory;
    public function __construct(string $xpub)
    {
        $this->xpub = $xpub;
        $this->factory = new HierarchicalKeyFactory();
    }

    public function generate(int $index): string
    {
        // Восстанавливаем ключ из xpub
        $extendedKey = $this->factory->fromExtended($this->xpub);

        // Деривируем дочерний ключ по индексу
        // Путь будет: [уже вшитый путь] + $index
        // То есть m/44'/195'/0'/0/$index
        $childKey = $extendedKey->deriveChild($index);

        // Получаем публичный ключ
        $publicKey = $childKey->getPublicKey()->getBuffer()->getHex();

        // Убираем первый байт (04) для хеширования
        $publicKeyWithoutPrefix = substr($publicKey, 2);

        // Конвертируем hex в бинарные данные для Keccak
        $binaryPublicKey = hex2bin($publicKeyWithoutPrefix);

        // Keccak-256 хеш от бинарных данных публичного ключа
        $hash = Keccak::hash($binaryPublicKey, 256);

        // Tron добавляет байт 0x41 перед адресом (версия)
        $hexWithPrefix = '41' . substr($hash, -40);

        return $this->base58check($hexWithPrefix);
    }

    /**
     * Base58Check кодирование для Tron адресов
     *
     * @param string $hex Адрес с префиксом 41 в hex (например, "41" + 40 hex символов)
     * @return string Base58Check закодированный адрес (начинается с T)
     */
    protected function base58check(string $hex): string  // убрать в Trait Base58Check
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
