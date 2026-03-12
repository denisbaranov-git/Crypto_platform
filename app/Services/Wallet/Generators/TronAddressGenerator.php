<?php

namespace App\Services\Wallet\Generators;

use kornrunner\Keccak;
use StephenHill\Base58;


class TronAddressGenerator
{
    public function generate(): array
    {
        $ec = new EC('secp256k1');
        $keyPair = $ec->genKeyPair();

        $privateKey = $keyPair->getPrivate()->toString(16, 64);
        $publicKey = $keyPair->getPublic()->encode('hex', false);

        $publicKey = substr($publicKey, 2); // убираем '04'

        $hash = Keccak::hash(substr($publicKey, 2), 256);
        $hex = '41' . substr($hash, 24);
        $address =  $this->base58check($hex);

        return [
            'public_key' => $publicKey,
            'private_key' => $privateKey,
            'address'     => $address,
        ];
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
