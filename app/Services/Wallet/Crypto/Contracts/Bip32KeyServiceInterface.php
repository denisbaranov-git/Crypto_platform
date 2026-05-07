<?php

declare(strict_types=1);

namespace App\Services\Wallet\Crypto\Contracts;

interface Bip32KeyServiceInterface
{
    // testnet флаг нужен для корректной сериализации xpub/tpub
    public function accountXpub(string $mnemonic, string $path, string $passphrase = '', bool $testnet = false): string;

    // network нужен, чтобы fromExtended() получил правильные magic bytes
    public function derivePublicKeyHex(string $xpub, string $network, int ...$segments): string;

    // отдельный метод для EVM/TRON, где нужен uncompressed public key
    public function deriveUncompressedPublicKeyHex(string $xpub, string $network, int ...$segments): string;
    public function normalizeUncompressedPublicKeyHex(string $publicKeyHex): string;

    public function expectedPrefixForNetwork(string $network): string;

    // CHANGE: метод бросает исключение при mismatch, поэтому он void
    public function assertXpubPrefix(string $xpub, string $network): void;
}
