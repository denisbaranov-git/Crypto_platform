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

        // Конвертируем в Ethereum-адрес
        $hash = Keccak::hash(substr($publicKey, 2), 256);

        return '0x' . substr($hash, 24);

    }
}
