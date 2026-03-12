<?php

namespace App\Services\Wallet\Generators;

use kornrunner\Keccak;

class EthereumAddressGenerator
{
    public function generate(): array
    {
        $ec = new EC('secp256k1');
        $keyPair = $ec->genKeyPair();

        $privateKey = $keyPair->getPrivate()->toString(16, 64);
        $publicKey = $keyPair->getPublic()->encode('hex', false);

        $publicKey = substr($publicKey, 2); // убираем '04'
        $hash = Keccak::hash(substr($publicKey, 2), 256);
        $address =  '0x' . substr($hash, 24);

        return [
            'public_key' => $publicKey,
            'private_key' => $privateKey,
            'address'     => $address,
        ];

    }
}
