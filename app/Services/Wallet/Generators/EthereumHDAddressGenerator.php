<?php

namespace App\Services\Wallet\Generators;

use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use kornrunner\Keccak;

class EthereumHDAddressGenerator
{
    private string $xpub;
    private HierarchicalKeyFactory $factory;

    public function __construct(string $xpub)
    {
        $this->xpub = $xpub;
        $this->factory = new HierarchicalKeyFactory();
    }

    /**
     * Генерирует адрес для пользователя по индексу
     *
     * @param int $index Индекс пользователя (0, 1, 2...)
     * @return string Адрес Ethereum
     */
    public function generate(int $index): string
    {
        // Восстанавливаем ключ из xpub
        $extendedKey = $this->factory->fromExtended($this->xpub);

        // Деривируем дочерний ключ по индексу
        // Путь будет: [уже вшитый путь] + $index
        // То есть m/44'/60'/0'/0/$index
        $childKey = $extendedKey->deriveChild($index);

        // Получаем публичный ключ
        $publicKey = $childKey->getPublicKey()->getBuffer()->getHex();

        // 4. Убираем первый байт (04) для хеширования
        $publicKeyWithoutPrefix = substr($publicKey, 2);

        // 5. Конвертируем hex в бинарные данные для Keccak
        $binaryPublicKey = hex2bin($publicKeyWithoutPrefix);

        // 6. Keccak-256 хеш от бинарных данных публичного ключа
        $hash = Keccak::hash($binaryPublicKey, 256);

        // 7. Адрес = последние 20 байт (40 hex символов) хеша с префиксом 0x
        return '0x' . substr($hash, -40);
    }
}
