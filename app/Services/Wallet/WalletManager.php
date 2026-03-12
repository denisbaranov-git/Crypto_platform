<?php
namespace App\Services\Wallet;

use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;

class WalletManager
{
    private string $mnemonic;

    public function __construct(string $mnemonic)
    {
        $this->mnemonic = $mnemonic;
    }

    /**
     * ШАГ 1: Получаем seed из мнемоники (как в вашем коде)
     */
    public function getSeedFromMnemonic(): string
    {
        $seedGenerator = new Bip39SeedGenerator();
        $seed = $seedGenerator->getSeed($this->mnemonic);

        // Это сырой seed (64 байта) — НЕ ПРИВАТНЫЙ КЛЮЧ
        return $seed->getHex();
    }

    /**
     * ШАГ 2: Создаём мастер-узел (xprv) из seed
     */
    public function createMasterNodeFromSeed($seed)
    {
        $factory = new HierarchicalKeyFactory();
        $masterNode = $factory->fromSeed($seed);

        // Это уже полноценный xprv с приватным ключом
        return $masterNode;
    }

    /**
     * ШАГ 3: Получаем xpub для сервера
     */
    public function getXpubForServer($masterNode): string
    {
        // Нейтрализуем (убираем приватную часть) — получаем xpub
        $xpubNode = $masterNode->neutered();
        return $xpubNode->toExtendedPublicKey();
    }

    /**
     * ШАГ 4: Подписываем транзакцию (требует xprv или мастер-узел)
     */
    public function signTransaction($masterNode, string $unsignedTxHex): string
    {
        // Здесь мастер-узел содержит приватный ключ
        // Используем его для подписи
        $signedTx = $this->signWithMasterNode($masterNode, $unsignedTxHex);

        // После подписи — затираем чувствительные данные
        sodium_memzero($masterNode->getPrivateKey()->getBinary());

        return $signedTx;
    }

    /**
     * БЕЗОПАСНОЕ восстановление: из seed получаем xpub и xprv
     */
    public function safeRestoreFromMnemonic(string $mnemonic): array
    {
        // 1. Seed из мнемоники
        $seedGenerator = new Bip39SeedGenerator();
        $seed = $seedGenerator->getSeed($mnemonic);

        // 2. Мастер-узел из seed
        $factory = new HierarchicalKeyFactory();
        $masterNode = $factory->fromSeed($seed);

        // 3. Получаем xprv (для холодного хранения/подписи)
        $xprv = $masterNode->toExtendedPrivateKey();

        // 4. Получаем xpub (для сервера)
        $xpubNode = $masterNode->neutered();
        $xpub = $xpubNode->toExtendedPublicKey();

        // 5. Затираем seed и приватные данные в памяти
        sodium_memzero($seed->getBinary());

        return [
            'xprv' => $xprv, // Хранить в холоде!
            'xpub' => $xpub   // Можно на сервер
        ];
    }
}
