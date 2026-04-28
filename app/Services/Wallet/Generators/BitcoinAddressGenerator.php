<?php

declare(strict_types=1);

namespace App\Services\Wallet\Generators;

use Elliptic\EC;
use StephenHill\Base58;

class BitcoinAddressGenerator
{
    // Типы адресов
    public const ADDRESS_TYPE_LEGACY = 'legacy';  // P2PKH (начинается с 1)
    public const ADDRESS_TYPE_SEGWIT = 'segwit';  // P2SH-P2WPKH (начинается с 3)
    public const ADDRESS_TYPE_NATIVE = 'native';  // Bech32 (начинается с bc1)

    private EC $ec;
    private Base58 $base58;

    public function __construct()
    {
        $this->ec = new EC('secp256k1');
        $this->base58 = new Base58();
    }

    /**
     * Генерирует новую пару ключей и возвращает адрес и приватный ключ
     *
     * @param array $options Дополнительные параметры:
     *   - type: 'legacy' | 'segwit' | 'native' (default: 'legacy')
     *   - testnet: bool (default: false)
     *   - compressed: bool (default: true)
     * @return array ['private_key' => string, 'address' => string, 'public_key' => string, 'wif' => string]
     */
    public function generate(array $options = []): array
    {
        // 1. Генерируем приватный ключ (64 hex символа = 32 байта)
        $privateKey = $this->generatePrivateKey();

        // 2. Получаем публичный ключ из приватного
        $keyPair = $this->ec->keyFromPrivate($privateKey);

        // 3. Определяем параметры
        $addressType = $options['type'] ?? self::ADDRESS_TYPE_LEGACY;
        $testnet = $options['testnet'] ?? false;
        $compressed = $options['compressed'] ?? true;

        // 4. Получаем публичный ключ в нужном формате
        if ($compressed) {
            $publicKey = $keyPair->getPublic()->encode('hex', true);
        } else {
            $publicKey = $keyPair->getPublic()->encode('hex', false);
        }

        // 5. Генерируем адрес в зависимости от типа
        $address = match($addressType) {
            self::ADDRESS_TYPE_LEGACY => $this->generateP2PKHAddress($publicKey, $testnet),
            self::ADDRESS_TYPE_SEGWIT => $this->generateP2SHAddress($publicKey, $testnet),
            self::ADDRESS_TYPE_NATIVE => $this->generateBech32Address($publicKey, $testnet),
            default => throw new \InvalidArgumentException("Unknown address type: {$addressType}")
        };

        // 6. Генерируем WIF (Wallet Import Format) для приватного ключа
        $wif = $this->privateKeyToWIF($privateKey, $compressed, $testnet);

        return [
            'private_key' => $privateKey,
            'public_key'  => $publicKey,
            'address'     => $address,
            'wif'         => $wif,
            'type'        => $addressType,
            'testnet'     => $testnet,
        ];
    }

    /**
     * Восстановить адрес из приватного ключа
     */
    public function addressFromPrivateKey(string $privateKey, array $options = []): string
    {
        $keyPair = $this->ec->keyFromPrivate($privateKey);

        $compressed = $options['compressed'] ?? true;
        $testnet = $options['testnet'] ?? false;
        $addressType = $options['type'] ?? self::ADDRESS_TYPE_LEGACY;

        $publicKey = $compressed
            ? $keyPair->getPublic()->encode('hex', true)
            : $keyPair->getPublic()->encode('hex', false);

        return match($addressType) {
            self::ADDRESS_TYPE_LEGACY => $this->generateP2PKHAddress($publicKey, $testnet),
            self::ADDRESS_TYPE_SEGWIT => $this->generateP2SHAddress($publicKey, $testnet),
            self::ADDRESS_TYPE_NATIVE => $this->generateBech32Address($publicKey, $testnet),
            default => throw new \InvalidArgumentException("Unknown address type: {$addressType}")
        };
    }

    /**
     * Генерация приватного ключа (64 hex символа = 32 байта)
     */
    private function generatePrivateKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Генерация P2PKH адреса (Legacy, начинается с 1 на mainnet, m/n на testnet)
     */
    private function generateP2PKHAddress(string $publicKey, bool $testnet): string
    {
        // 1. SHA-256 хеш публичного ключа
        $sha256 = hash('sha256', hex2bin($publicKey), true);

        // 2. RIPEMD-160 хеш от SHA-256
        $ripeMd160 = hash('ripemd160', $sha256, true);

        // 3. Добавляем версию (0x00 для mainnet, 0x6f для testnet)
        $networkByte = $testnet ? "\x6f" : "\x00";
        $versionedPayload = $networkByte . $ripeMd160;

        // 4. Двойной SHA-256 для контрольной суммы
        $checksum = $this->doubleSha256($versionedPayload);

        // 5. Добавляем первые 4 байта контрольной суммы
        $binaryAddress = $versionedPayload . substr($checksum, 0, 4);

        // 6. Кодируем в Base58
        return $this->base58->encode($binaryAddress);
    }

    /**
     * Генерация P2SH-P2WPKH адреса (SegWit wrapped, начинается с 3 на mainnet, 2 на testnet)
     */
    private function generateP2SHAddress(string $publicKey, bool $testnet): string
    {
        // Для сжатого публичного ключа
        $compressedPubKey = hex2bin($publicKey);
        if (strlen($compressedPubKey) === 65) {
            // Если ключ не сжатый, сжимаем его
            $prefix = $compressedPubKey[64] % 2 === 0 ? "\x02" : "\x03";
            $compressedPubKey = $prefix . substr($compressedPubKey, 1, 32);
        }

        // 1. SHA-256 публичного ключа
        $sha256 = hash('sha256', $compressedPubKey, true);

        // 2. RIPEMD-160 для получения HASH160
        $pubKeyHash = hash('ripemd160', $sha256, true);

        // 3. Создаем witness program: 0x00 0x14 <20-byte-pubkey-hash>
        $witnessProgram = "\x00\x14" . $pubKeyHash;

        // 4. SHA-256 witness program
        $scriptHash = hash('sha256', $witnessProgram, true);

        // 5. RIPEMD-160
        $scriptHash160 = hash('ripemd160', $scriptHash, true);

        // 6. Добавляем версию (0x05 для mainnet, 0xc4 для testnet)
        $networkByte = $testnet ? "\xc4" : "\x05";
        $versionedPayload = $networkByte . $scriptHash160;

        // 7. Двойной SHA-256 для контрольной суммы
        $checksum = $this->doubleSha256($versionedPayload);

        // 8. Добавляем контрольную сумму и кодируем в Base58
        $binaryAddress = $versionedPayload . substr($checksum, 0, 4);

        return $this->base58->encode($binaryAddress);
    }

    /**
     * Генерация Bech32 адреса (Native SegWit, начинается с bc1 на mainnet, tb1 на testnet)
     */
    private function generateBech32Address(string $publicKey, bool $testnet): string
    {
        // Используем сжатый публичный ключ
        $compressedPubKey = hex2bin($publicKey);
        if (strlen($compressedPubKey) === 65) {
            $prefix = $compressedPubKey[64] % 2 === 0 ? "\x02" : "\x03";
            $compressedPubKey = $prefix . substr($compressedPubKey, 1, 32);
        }

        // 1. SHA-256 публичного ключа
        $sha256 = hash('sha256', $compressedPubKey, true);

        // 2. RIPEMD-160 для получения HASH160
        $pubKeyHash = hash('ripemd160', $sha256, true);

        // 3. Создаем witness program: версия 0 (0x00) + 20 байт хеша
        $witnessProgram = "\x00" . $pubKeyHash;

        // 4. Конвертируем из 8-битных байт в 5-битные слова
        $fiveBitWords = $this->convertBits($witnessProgram, 8, 5, true);

        // 5. HRP (Human Readable Part)
        $hrp = $testnet ? 'tb' : 'bc';

        // 6. Кодируем в Bech32
        return $this->encodeBech32($hrp, $fiveBitWords);
    }

    /**
     * Конвертирует приватный ключ в WIF (Wallet Import Format)
     */
    private function privateKeyToWIF(string $privateKey, bool $compressed, bool $testnet): string
    {
        // 1. Добавляем версию (0x80 для mainnet, 0xef для testnet)
        $networkByte = $testnet ? "\xef" : "\x80";

        // 2. Приватный ключ в бинарном виде (32 байта)
        $keyBytes = hex2bin($privateKey);

        // 3. Добавляем суффикс 0x01 для сжатых ключей
        $suffix = $compressed ? "\x01" : '';

        // 4. Собираем данные: версия + ключ + суффикс
        $data = $networkByte . $keyBytes . $suffix;

        // 5. Двойной SHA-256 для контрольной суммы
        $checksum = $this->doubleSha256($data);

        // 6. Добавляем первые 4 байта контрольной суммы
        $wifBinary = $data . substr($checksum, 0, 4);

        // 7. Кодируем в Base58
        return $this->base58->encode($wifBinary);
    }

    /**
     * Двойной SHA-256 хеш
     */
    private function doubleSha256(string $data): string
    {
        $hash1 = hash('sha256', $data, true);
        return hash('sha256', $hash1, true);
    }

    /**
     * Конвертирует биты из одного формата в другой (для Bech32)
     */
    private function convertBits(string $data, int $fromBits, int $toBits, bool $pad = true): array
    {
        $acc = 0;
        $bits = 0;
        $ret = [];
        $maxv = (1 << $toBits) - 1;
        $maxAcc = (1 << ($fromBits + $toBits - 1)) - 1;

        $dataArray = str_split($data);
        $dataLen = count($dataArray);

        for ($i = 0; $i < $dataLen; $i++) {
            $value = ord($data[$i]);

            if ($value >> $fromBits !== 0) {
                throw new \InvalidArgumentException('Invalid value: exceeds fromBits');
            }

            $acc = (($acc << $fromBits) | $value) & $maxAcc;
            $bits += $fromBits;

            while ($bits >= $toBits) {
                $bits -= $toBits;
                $ret[] = ($acc >> $bits) & $maxv;
            }
        }

        if ($pad) {
            if ($bits > 0) {
                $ret[] = ($acc << ($toBits - $bits)) & $maxv;
            }
        } elseif ($bits >= $fromBits || ((($acc << ($toBits - $bits)) & $maxv) !== 0)) {
            throw new \InvalidArgumentException('Invalid padding');
        }

        return $ret;
    }

    /**
     * Кодирует данные в Bech32
     */
    private function encodeBech32(string $hrp, array $data): string
    {
        $charset = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
        $generator = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];

        // Расширяем HRP
        $hrpExpanded = $this->hrpExpand($hrp);

        // Объединяем HRP и данные
        $combined = array_merge($hrpExpanded, $data);

        // Вычисляем контрольную сумму
        $checksum = $this->bech32Polymod(array_merge($combined, [0, 0, 0, 0, 0, 0])) ^ 1;

        // Извлекаем 6 значений контрольной суммы
        $checksumBytes = [];
        for ($i = 0; $i < 6; $i++) {
            $checksumBytes[] = ($checksum >> (5 * (5 - $i))) & 31;
        }

        // Формируем итоговый адрес
        $result = $hrp . '1';
        foreach (array_merge($data, $checksumBytes) as $value) {
            $result .= $charset[$value];
        }

        return $result;
    }

    /**
     * Полином для Bech32 контрольной суммы
     */
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

    /**
     * Расширяет HRP для Bech32
     */
    private function hrpExpand(string $hrp): array
    {
        $result = [];
        $len = strlen($hrp);

        for ($i = 0; $i < $len; $i++) {
            $result[] = ord($hrp[$i]) >> 5;
        }
        $result[] = 0;
        for ($i = 0; $i < $len; $i++) {
            $result[] = ord($hrp[$i]) & 31;
        }

        return $result;
    }
}
