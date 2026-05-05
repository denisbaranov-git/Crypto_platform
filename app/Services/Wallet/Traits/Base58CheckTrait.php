<?php

declare(strict_types=1);

namespace App\Services\Wallet\Traits;

use StephenHill\Base58;

trait Base58CheckTrait
{
    protected function base58checkEncode(string $hex): string
    {
        $bin = hex2bin($hex);

        if ($bin === false) {
            throw new \InvalidArgumentException('Invalid hex input for Base58Check encoding.');
        }

        $hash1 = hash('sha256', $bin, true);
        $hash2 = hash('sha256', $hash1, true);
        $checksum = substr($hash2, 0, 4);

        $base58 = new Base58();

        return $base58->encode($bin . $checksum);
    }

    protected function base58checkDecode(string $base58String): string
    {
        $base58 = new Base58();
        $binary = $base58->decode($base58String);

        if (strlen($binary) < 5) {
            throw new \RuntimeException('Invalid Base58Check string.');
        }

        $data = substr($binary, 0, -4);
        $checksum = substr($binary, -4);

        $hash1 = hash('sha256', $data, true);
        $hash2 = hash('sha256', $hash1, true);
        $calculatedChecksum = substr($hash2, 0, 4);

        if (!hash_equals($checksum, $calculatedChecksum)) {
            throw new \RuntimeException('Invalid Base58Check checksum.');
        }

        return bin2hex($data);
    }
}
