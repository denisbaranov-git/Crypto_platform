<?php

declare(strict_types=1);

namespace App\Services\Wallet\Generators;

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

    public function generate(int $index): string
    {
        $publicKeyHex = $this->bip32->derivePublicKeyHex($this->xpub, $this->network, $index);

        if (strlen($publicKeyHex) !== 130 || !str_starts_with($publicKeyHex, '04')) {
            throw new \RuntimeException('Expected an uncompressed 65-byte public key.');
        }

        $binaryPublicKey = hex2bin(substr($publicKeyHex, 2));

        if ($binaryPublicKey === false) {
            throw new \RuntimeException('Unable to convert public key to binary.');
        }

        $hash = Keccak::hash($binaryPublicKey, 256);
        $hexWithPrefix = '41' . substr($hash, -40);

        return $this->base58checkEncode($hexWithPrefix);
    }
}
