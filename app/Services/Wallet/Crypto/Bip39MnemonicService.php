<?php

declare(strict_types=1);

namespace App\Services\Wallet\Crypto;

use BitWasp\Bitcoin\Crypto\Random\Random;
use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;
use BitWasp\Buffertools\BufferInterface;
use Illuminate\Support\Facades\Crypt;

final class Bip39MnemonicService
{
    public function generateMnemonic(): string
    {
         $random = new Random();
         $entropy = $random->bytes(32);

         $bip39 = MnemonicFactory::bip39();

         //$mnemonic = $bip39->entropyToMnemonic($entropy);
         //$encryptedMnemonic = Crypt::encryptString($mnemonic);

         return $bip39->entropyToMnemonic($entropy);
    }

    public function generateSeed(string $mnemonic, string $passphrase = '')//: string
    {
        $mnemonic = trim(preg_replace('/\s+/u', ' ', $mnemonic) ?? $mnemonic);

        if ($mnemonic === '') {
            throw new \InvalidArgumentException('Mnemonic is empty.');
        }

        $generator = new Bip39SeedGenerator();
        $seed = $generator->getSeed($mnemonic, $passphrase);
        //return $generator->getSeed($mnemonic, $passphrase);
        return $seed->getBinary();

    }
}
