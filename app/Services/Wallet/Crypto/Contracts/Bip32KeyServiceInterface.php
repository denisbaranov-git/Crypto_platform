<?php

declare(strict_types=1);

namespace App\Services\Wallet\Crypto\Contracts;

interface Bip32KeyServiceInterface
{
    public function accountXpub(string $mnemonic, string $path, string $passphrase = '', bool $testnet = false): string;

    public function derivePublicKeyHex(string $xpub, string $network, int ...$segments): string;

    public function expectedPrefixForNetwork(string $network): string;

    public function assertXpubPrefix(string $xpub, string $network): void;
}
