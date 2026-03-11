<?php

namespace App\Services\Wallet\Generators;

//use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use kornrunner\Keccak;
use StephenHill\Base58;


class TronAddressGenerator
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

//        $hash = Keccak::hash($publicKeyWithoutPrefix, 256, true);
//        $addressBin = "\x41" . substr($hash, -20);
        $hash = Keccak::hash(substr($publicKey, 2), 256);
        $hex = '41' . substr($hash, 24);

        return $this->base58check($hex);
    }

    protected function base58check(string $hex): string
    {
        $bin = hex2bin($hex);

        $checksum = substr( hash('sha256', hash('sha256', $bin, true), true),0,4);

        $bin .= $checksum;

        $base58 = new Base58();

        return $base58->encode($bin);
    }
}
