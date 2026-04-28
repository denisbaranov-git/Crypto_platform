<?php

declare(strict_types=1);

namespace App\Services\Wallet\Generators;

use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use StephenHill\Base58;

class BitcoinHDAddressGenerator
{
    use \App\Services\Wallet\Traits\Base58CheckTrait;

    private string $xpub;
    private Base58 $base58;

    public function __construct(string $xpub)
    {
        $this->xpub = $xpub;
        $this->base58 = new Base58();
    }

    /**
     * Генерирует Bitcoin адрес из HD-кошелька
     * Путь: m/44'/0'/0'/0/$index (для mainnet)
     * Путь: m/44'/1'/0'/0/$index (для testnet)
     *
     * @param int $index Индекс адреса
     * @param bool $testnet Использовать testnet
     * @param string $type Тип адреса: 'legacy', 'segwit', 'native'
     * @return array Информация об адресе
     */
    public function generate(int $index, bool $testnet = false, string $type = 'segwit'): array
    {
        // Восстанавливаем ключ из xpub
        $extendedKey = HierarchicalKeyFactory::fromExtended($this->xpub);

        // Деривируем дочерний ключ
        $childKey = $extendedKey->deriveChild($index);

        // Получаем публичный ключ
        $publicKey = $childKey->getPublicKey()->getHex();

        // Генерируем адрес в зависимости от типа
        $address = match($type) {
            'legacy' => $this->generateP2PKHAddress($publicKey, $testnet),
            'segwit' => $this->generateP2SHAddress($publicKey, $testnet),
            'native' => $this->generateBech32Address($publicKey, $testnet),
            default => throw new \InvalidArgumentException("Unknown address type: {$type}")
        };

        return [
            'address' => $address,
            'type' => $type,
            'path' => $testnet ? "m/44'/1'/0'/0/{$index}" : "m/44'/0'/0'/0/{$index}",
            'testnet' => $testnet,
            'index' => $index,
        ];
    }

    /**
     * Генерирует P2PKH (Legacy) адрес
     */
    private function generateP2PKHAddress(string $publicKey, bool $testnet): string
    {
        $pubKeyBin = hex2bin($publicKey);
        $pubKeyCompressed = $this->compressPublicKey($pubKeyBin);

        $sha256 = hash('sha256', $pubKeyCompressed, true);
        $ripeMd160 = hash('ripemd160', $sha256, true);

        $networkByte = $testnet ? "\x6f" : "\x00";
        $versionedPayload = $networkByte . $ripeMd160;

        $checksum = $this->doubleSha256($versionedPayload);
        $binaryAddress = $versionedPayload . substr($checksum, 0, 4);

        return $this->base58->encode($binaryAddress);
    }

    /**
     * Генерирует P2SH (SegWit wrapped) адрес
     */
    private function generateP2SHAddress(string $publicKey, bool $testnet): string
    {
        $pubKeyBin = hex2bin($publicKey);
        $pubKeyCompressed = $this->compressPublicKey($pubKeyBin);

        $sha256 = hash('sha256', $pubKeyCompressed, true);
        $pubKeyHash = hash('ripemd160', $sha256, true);

        $witnessProgram = "\x00\x14" . $pubKeyHash;
        $scriptHash = hash('sha256', $witnessProgram, true);
        $scriptHash160 = hash('ripemd160', $scriptHash, true);

        $networkByte = $testnet ? "\xc4" : "\x05";
        $versionedPayload = $networkByte . $scriptHash160;

        $checksum = $this->doubleSha256($versionedPayload);
        $binaryAddress = $versionedPayload . substr($checksum, 0, 4);

        return $this->base58->encode($binaryAddress);
    }

    /**
     * Генерирует Bech32 (Native SegWit) адрес
     */
    private function generateBech32Address(string $publicKey, bool $testnet): string
    {
        $pubKeyBin = hex2bin($publicKey);
        $pubKeyCompressed = $this->compressPublicKey($pubKeyBin);

        $sha256 = hash('sha256', $pubKeyCompressed, true);
        $pubKeyHash = hash('ripemd160', $sha256, true);

        $witnessProgram = "\x00" . $pubKeyHash;
        $fiveBitWords = $this->convertBits($witnessProgram, 8, 5, true);

        $hrp = $testnet ? 'tb' : 'bc';

        return $this->encodeBech32($hrp, $fiveBitWords);
    }

    /**
     * Сжимает публичный ключ
     */
    private function compressPublicKey(string $publicKey): string
    {
        // Если уже сжатый (33 байта) - возвращаем как есть
        if (strlen($publicKey) === 33) {
            return $publicKey;
        }

        // Несжатый (65 байт) - сжимаем
        $x = substr($publicKey, 1, 32);
        $y = substr($publicKey, 33, 32);

        $prefix = (ord($y[31]) % 2 === 0) ? "\x02" : "\x03";

        return $prefix . $x;
    }

    /**
     * Двойной SHA-256
     */
    private function doubleSha256(string $data): string
    {
        return hash('sha256', hash('sha256', $data, true), true);
    }

    /**
     * Конвертация бит для Bech32
     */
    private function convertBits(string $data, int $fromBits, int $toBits, bool $pad = true): array
    {
        $acc = 0;
        $bits = 0;
        $ret = [];
        $maxv = (1 << $toBits) - 1;

        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $value = ord($data[$i]);
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

    /**
     * Bech32 кодирование
     */
    private function encodeBech32(string $hrp, array $data): string
    {
        $charset = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

        // Расширяем HRP
        $expanded = [];
        for ($i = 0, $len = strlen($hrp); $i < $len; $i++) {
            $expanded[] = ord($hrp[$i]) >> 5;
        }
        $expanded[] = 0;
        for ($i = 0, $len = strlen($hrp); $i < $len; $i++) {
            $expanded[] = ord($hrp[$i]) & 31;
        }

        // Вычисляем контрольную сумму
        $combined = array_merge($expanded, $data, [0, 0, 0, 0, 0, 0]);
        $checksum = $this->bech32Polymod($combined) ^ 1;

        // Добавляем контрольную сумму
        $checksumBytes = [];
        for ($i = 0; $i < 6; $i++) {
            $checksumBytes[] = ($checksum >> (5 * (5 - $i))) & 31;
        }

        // Формируем адрес
        $result = $hrp . '1';
        foreach (array_merge($data, $checksumBytes) as $value) {
            $result .= $charset[$value];
        }

        return $result;
    }

    private function bech32Polymod(array $values): int
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
