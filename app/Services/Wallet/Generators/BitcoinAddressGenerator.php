<?php

namespace App\Services\Wallet\Generators;

use App\Contracts\AddressGeneratorInterface;
use Elliptic\EC;
use StephenHill\Base58;
use kornrunner\Keccak; // Не используется, но оставляем для совместимости с интерфейсом

class BitcoinAddressGenerator implements AddressGeneratorInterface
{
    private EC $ec;
    private Base58 $base58;

    /**
     * Типы адресов Bitcoin
     */
    const ADDRESS_TYPE_LEGACY = 'p2pkh';    // Начинается с 1
    const ADDRESS_TYPE_SEGWIT = 'p2sh';      // Начинается с 3
    const ADDRESS_TYPE_NATIVE = 'bech32';    // Начинается с bc1

    public function __construct()
    {
        $this->ec = new EC('secp256k1');
        $this->base58 = new Base58();
    }

    /**
     * Генерирует новую пару ключей и возвращает адрес и приватный ключ
     *
     * @param array $options Дополнительные параметры (type, testnet, compressed)
     * @return array ['private_key' => string, 'address' => string]
     */
    public function generate(array $options = []): array
    {
        // 1. Генерируем приватный ключ (256 бит)
        $privateKey = $this->generatePrivateKey();

        // 2. Получаем публичный ключ
        $keyPair = $this->ec->keyFromPrivate($privateKey);
        $publicKey = $keyPair->getPublic()->encode('hex', false); // uncompressed

        // 3. Определяем тип адреса
        $addressType = $options['type'] ?? self::ADDRESS_TYPE_LEGACY;
        $testnet = $options['testnet'] ?? false;
        $compressed = $options['compressed'] ?? true; // Bitcoin обычно использует сжатые ключи

        // 4. Генерируем адрес в зависимости от типа
        $address = match($addressType) {
            self::ADDRESS_TYPE_LEGACY => $this->generateP2PKHAddress($publicKey, $compressed, $testnet),
            self::ADDRESS_TYPE_SEGWIT => $this->generateP2SHAddress($publicKey, $compressed, $testnet),
            self::ADDRESS_TYPE_NATIVE => $this->generateBech32Address($publicKey, $compressed, $testnet),
            default => $this->generateP2PKHAddress($publicKey, $compressed, $testnet)
        };

        // 5. Генерируем WIF (Wallet Import Format) для приватного ключа
        $wif = $this->privateKeyToWIF($privateKey, $compressed, $testnet);

        return [
            'private_key' => $privateKey,      // hex-формат для совместимости с интерфейсом
            'wif' => $wif,                      // Bitcoin-формат
            'address' => $address,
            'public_key' => $publicKey,
            'type' => $addressType
        ];
    }

    /**
     * Восстанавливает адрес из приватного ключа
     */
    public function addressFromPrivateKey(string $privateKey): string
    {
        // Этот метод нужен для интерфейса, но в Bitcoin лучше использовать generate с опциями
        return $this->generate(['private_key' => $privateKey])['address'];
    }

    /**
     * Генерация приватного ключа (64 hex символа)
     */
    private function generatePrivateKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Генерация P2PKH адреса (Legacy, начинается с 1)
     */
    private function generateP2PKHAddress(string $publicKey, bool $compressed, bool $testnet): string
    {
        // 1. SHA-256 хеш публичного ключа
        $sha256 = hash('sha256', hex2bin($publicKey), true);

        // 2. RIPEMD-160 хеш от SHA-256
        $ripeMd160 = hash('ripemd160', $sha256, true);

        // 3. Добавляем версию (0x00 для mainnet, 0x6f для testnet)
        $networkByte = $testnet ? "\x6f" : "\x00";
        $withVersion = $networkByte . $ripeMd160;

        // 4. Двойной SHA-256 для контрольной суммы
        $checksum = $this->doubleSha256($withVersion);

        // 5. Добавляем первые 4 байта контрольной суммы
        $binaryAddress = $withVersion . substr($checksum, 0, 4);

        // 6. Кодируем в Base58
        return $this->base58->encode($binaryAddress);
    }

    /**
     * Генерация P2SH адреса (SegWit, начинается с 3)
     */
    private function generateP2SHAddress(string $publicKey, bool $compressed, bool $testnet): string
    {
        // Для P2SH мы создаём redeem script: OP_0 <20-byte-hash>

        // 1. Получаем хеш публичного ключа (как в P2PKH)
        $sha256 = hash('sha256', hex2bin($publicKey), true);
        $pubKeyHash = hash('ripemd160', $sha256, true);

        // 2. Создаём redeem script: 0x0014 + pubKeyHash
        $redeemScript = "\x00\x14" . $pubKeyHash;

        // 3. SHA-256 redeem script
        $scriptHash = hash('sha256', $redeemScript, true);

        // 4. RIPEMD-160
        $scriptHash160 = hash('ripemd160', $scriptHash, true);

        // 5. Добавляем версию (0x05 для mainnet, 0xc4 для testnet)
        $networkByte = $testnet ? "\xc4" : "\x05";
        $withVersion = $networkByte . $scriptHash160;

        // 6. Двойной SHA-256 для контрольной суммы
        $checksum = $this->doubleSha256($withVersion);

        // 7. Добавляем контрольную сумму и кодируем в Base58
        $binaryAddress = $withVersion . substr($checksum, 0, 4);
        return $this->base58->encode($binaryAddress);
    }

    /**
     * Генерация Bech32 адреса (Native SegWit, начинается с bc1)
     */
    private function generateBech32Address(string $publicKey, bool $compressed, bool $testnet): string
    {
        // Bech32 адреса используют witness program

        // 1. Получаем хеш публичного ключа (20 байт)
        $sha256 = hash('sha256', hex2bin($publicKey), true);
        $pubKeyHash = hash('ripemd160', $sha256, true);

        // 2. Witness program: версия 0 (0x00) + 20 байт хеша
        $witnessProgram = "\x00" . $pubKeyHash;

        // 3. Конвертируем 8-битные байты в 5-битные слова для Bech32
        $fiveBitData = $this->convertBits($witnessProgram, 8, 5, true);

        // 4. HRP (Human Readable Part): bc для mainnet, tb для testnet
        $hrp = $testnet ? 'tb' : 'bc';

        // 5. Создаём Bech32 адрес
        return $this->encodeBech32($hrp, $fiveBitData);
    }

    /**
     * Конвертирует приватный ключ в WIF (Wallet Import Format)
     */
    private function privateKeyToWIF(string $privateKey, bool $compressed, bool $testnet): string
    {
        // 1. Добавляем версию (0x80 для mainnet, 0xef для testnet)
        $networkByte = $testnet ? "\xef" : "\x80";
        $keyBytes = hex2bin($privateKey);

        // 2. Добавляем суффикс для сжатых ключей
        $suffix = $compressed ? "\x01" : '';

        // 3. Собираем данные для хеширования
        $data = $networkByte . $keyBytes . $suffix;

        // 4. Двойной SHA-256 для контрольной суммы
        $checksum = $this->doubleSha256($data);

        // 5. Добавляем первые 4 байта контрольной суммы
        $wifBinary = $data . substr($checksum, 0, 4);

        // 6. Кодируем в Base58
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

        for ($i = 0; $i < strlen($data); $i++) {
            $value = ord($data[$i]);
            if ($value < 0 || $value >> $fromBits != 0) {
                throw new \Exception('Invalid byte value');
            }
            $acc = ($acc << $fromBits) | $value;
            $bits += $fromBits;
            while ($bits >= $toBits) {
                $bits -= $toBits;
                $ret[] = ($acc >> $bits) & $maxv;
            }
        }

        if ($pad) {
            if ($bits) {
                $ret[] = ($acc << ($toBits - $bits)) & $maxv;
            }
        } else if ($bits >= $fromBits || ((($acc << ($toBits - $bits)) & $maxv) != 0)) {
            throw new \Exception('Invalid padding');
        }

        return $ret;
    }

    /**
     * Кодирует данные в Bech32
     */
    private function encodeBech32(string $hrp, array $data): string
    {
        // Bech32 charset
        $charset = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

        // Константы для полинома
        $generator = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];

        // Функция для вычисления полинома
        $polyMod = function($values) use ($generator) {
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
        };

        // Добавляем контрольную сумму
        $values = array_merge($data, [0, 0, 0, 0, 0, 0]);
        $polymod = $polyMod(array_merge($this->hrpExpand($hrp), $values)) ^ 1;

        for ($i = 0; $i < 6; $i++) {
            $data[] = ($polymod >> (5 * (5 - $i))) & 31;
        }

        // Кодируем
        $result = $hrp . '1';
        foreach ($data as $value) {
            $result .= $charset[$value];
        }

        return $result;
    }

    /**
     * Расширяет HRP для Bech32
     */
    private function hrpExpand(string $hrp): array
    {
        $result = [];
        for ($i = 0; $i < strlen($hrp); $i++) {
            $result[] = ord($hrp[$i]) >> 5;
        }
        $result[] = 0;
        for ($i = 0; $i < strlen($hrp); $i++) {
            $result[] = ord($hrp[$i]) & 31;
        }
        return $result;
    }
}
