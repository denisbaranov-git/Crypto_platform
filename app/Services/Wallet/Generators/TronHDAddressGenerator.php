<?php

declare(strict_types=1);

namespace App\Services\Wallet\Generators;

use App\Domain\Wallet\Services\GeneratedAddress;
use App\Services\Wallet\Crypto\Contracts\Bip32KeyServiceInterface;
use kornrunner\Keccak;

final class TronHDAddressGenerator
{
    use \App\Services\Wallet\Traits\Base58CheckTrait;

    public function __construct(
        private readonly Bip32KeyServiceInterface $bip32,
        private readonly string $xpub,
        private readonly string $network,
    ) {
    }

    public function generate(int $index): GeneratedAddress
    {
        // normalise to uncompressed before hashing
        $publicKeyHex = $this->bip32->derivePublicKeyHex($this->xpub, $this->network, 0, $index);
        $publicKeyHex = $this->bip32->normalizeUncompressedPublicKeyHex($publicKeyHex);

        $binaryPublicKey = hex2bin(substr($publicKeyHex, 2));
        if ($binaryPublicKey === false) {
            throw new \RuntimeException('Unable to convert public key to binary.');
        }

        $hash = Keccak::hash($binaryPublicKey, 256);
        $hexWithPrefix = '41' . substr($hash, -40);

        return new GeneratedAddress(
            $this->base58checkEncode($hexWithPrefix),
            $this->isTestnetNetwork()
                ? "m/44'/1'/0'/0/{$index}"
                : "m/44'/195'/0'/0/{$index}",
            0,
            $index
        );
    }

    private function isTestnetNetwork(): bool
    {
        return str_contains($this->network, 'testnet')
            || str_contains($this->network, 'sepolia')
            || str_contains($this->network, 'nile')
            || str_contains($this->network, 'amoy');
    }
}
