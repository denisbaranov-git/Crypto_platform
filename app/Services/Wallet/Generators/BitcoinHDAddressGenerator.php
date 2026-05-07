<?php

declare(strict_types=1);

namespace App\Services\Wallet\Generators;

use App\Domain\Wallet\Services\GeneratedAddress;
use App\Services\Wallet\Crypto\Contracts\Bip32KeyServiceInterface;
use StephenHill\Base58;

final class BitcoinHDAddressGenerator
{
    use \App\Services\Wallet\Traits\Base58CheckTrait;

    private Base58 $base58;

    public function __construct(
        private readonly Bip32KeyServiceInterface $bip32,
        private readonly string $xpub,
    ) {
        $this->base58 = new Base58();
    }

    public function generate(
        int $index,
        bool $testnet = false,
        string $type = 'segwit',
        int $chain = 0
    ): GeneratedAddress {
        if (!in_array($chain, [0, 1], true)) {
            throw new \InvalidArgumentException('Chain must be 0 (external) or 1 (change).');
        }

        if (!in_array($type, ['legacy', 'segwit', 'native'], true)) {
            throw new \InvalidArgumentException("Unknown address type: {$type}");
        }

        $network = $testnet ? 'bitcoin_testnet' : 'bitcoin';
        $publicKeyHex = $this->bip32->derivePublicKeyHex($this->xpub, $network, $chain, $index);

        $address = match ($type) {
            'legacy' => $this->generateP2PKHAddress($publicKeyHex, $testnet),
            'segwit' => $this->generateP2SHAddress($publicKeyHex, $testnet),
            'native' => $this->generateBech32Address($publicKeyHex, $testnet),
        };

        return new GeneratedAddress(
            $address,
            $testnet
                ? "m/44'/1'/0'/{$chain}/{$index}"
                : "m/44'/0'/0'/{$chain}/{$index}",
            $chain,
            $index
        );
    }

    private function generateP2PKHAddress(string $publicKeyHex, bool $testnet): string
    {
        $pubKeyBin = hex2bin($publicKeyHex);

        if ($pubKeyBin === false) {
            throw new \RuntimeException('Invalid public key hex.');
        }

        $pubKeyCompressed = $this->compressPublicKey($pubKeyBin);
        $hash160 = hash('ripemd160', hash('sha256', $pubKeyCompressed, true), true);

        $networkByte = $testnet ? "\x6f" : "\x00";

        return $this->base58CheckEncodeBinary($networkByte . $hash160);
    }

    private function generateP2SHAddress(string $publicKeyHex, bool $testnet): string
    {
        $pubKeyBin = hex2bin($publicKeyHex);

        if ($pubKeyBin === false) {
            throw new \RuntimeException('Invalid public key hex.');
        }

        $pubKeyCompressed = $this->compressPublicKey($pubKeyBin);
        $pubKeyHash = hash('ripemd160', hash('sha256', $pubKeyCompressed, true), true);

        $redeemScript = hex2bin('0014' . bin2hex($pubKeyHash));

        if ($redeemScript === false) {
            throw new \RuntimeException('Failed to build redeem script.');
        }

        $scriptHash160 = hash('ripemd160', hash('sha256', $redeemScript, true), true);
        $networkByte = $testnet ? "\xc4" : "\x05";

        return $this->base58CheckEncodeBinary($networkByte . $scriptHash160);
    }

    private function generateBech32Address(string $publicKeyHex, bool $testnet): string
    {
        $pubKeyBin = hex2bin($publicKeyHex);

        if ($pubKeyBin === false) {
            throw new \RuntimeException('Invalid public key hex.');
        }

        $pubKeyCompressed = $this->compressPublicKey($pubKeyBin);
        $hash160 = hash('ripemd160', hash('sha256', $pubKeyCompressed, true), true);

        $program = array_merge([0], $this->convertBits($hash160, 8, 5, true));
        $hrp = $testnet ? 'tb' : 'bc';

        return $this->encodeBech32($hrp, $program);
    }

    private function compressPublicKey(string $publicKey): string
    {
        if (strlen($publicKey) === 33) {
            return $publicKey;
        }

        if (strlen($publicKey) !== 65) {
            throw new \RuntimeException('Unexpected public key length.');
        }

        $x = substr($publicKey, 1, 32);
        $y = substr($publicKey, 33, 32);

        $prefix = (ord($y[31]) % 2 === 0) ? "\x02" : "\x03";

        return $prefix . $x;
    }

    private function base58CheckEncodeBinary(string $payload): string
    {
        $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);

        return $this->base58->encode($payload . $checksum);
    }

    private function convertBits(string $data, int $fromBits, int $toBits, bool $pad = true): array
    {
        $acc = 0;
        $bits = 0;
        $ret = [];
        $maxv = (1 << $toBits) - 1;

        foreach (array_values(unpack('C*', $data)) as $value) {
            $acc = ($acc << $fromBits) | $value;
            $bits += $fromBits;

            while ($bits >= $toBits) {
                $bits -= $toBits;
                $ret[] = ($acc >> $bits) & $maxv;
            }
        }

        if ($pad && $bits) {
            $ret[] = ($acc << ($toBits - $bits)) & $maxv;
        }

        return $ret;
    }

    private function encodeBech32(string $hrp, array $data): string
    {
        $charset = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
        $combined = array_merge($data, $this->createChecksum($hrp, $data));

        $result = $hrp . '1';

        foreach ($combined as $value) {
            $result .= $charset[$value];
        }

        return $result;
    }

    private function createChecksum(string $hrp, array $data): array
    {
        $values = array_merge($this->expandHrp($hrp), $data, [0, 0, 0, 0, 0, 0]);
        $polymod = $this->polymod($values) ^ 1;

        $checksum = [];
        for ($i = 0; $i < 6; $i++) {
            $checksum[] = ($polymod >> (5 * (5 - $i))) & 31;
        }

        return $checksum;
    }

    private function expandHrp(string $hrp): array
    {
        $result = [];

        foreach (str_split($hrp) as $char) {
            $result[] = ord($char) >> 5;
        }

        $result[] = 0;

        foreach (str_split($hrp) as $char) {
            $result[] = ord($char) & 31;
        }

        return $result;
    }

    private function polymod(array $values): int
    {
        $generator = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];
        $chk = 1;

        foreach ($values as $value) {
            $top = $chk >> 25;
            $chk = (($chk & 0x1ffffff) << 5) ^ $value;

            for ($i = 0; $i < 5; $i++) {
                if (($top >> $i) & 1) {
                    $chk ^= $generator[$i];
                }
            }
        }

        return $chk;
    }
}
