<?php

namespace App\Services\Wallet\Traits;

use StephenHill\Base58;

trait Base58Check
{
    /**
     * Кодирует hex-строку в Base58Check формат (с контрольной суммой)
     *
     * @param string $hex Hex-строка для кодирования
     * @param int $version Версия адреса (опционально)
     * @return string Base58Check закодированная строка
     */
    protected function base58CheckEncode(string $hex, ?string $version = null): string
    {
        // Добавляем версию, если указана
        if ($version !== null) {
            $hex = $version . $hex;
        }

        // Конвертируем hex в бинарные данные
        $bin = hex2bin($hex);

        // Вычисляем контрольную сумму (первые 4 байта двойного SHA256)
        $checksum = $this->doubleSha256($bin, 4);

        // Добавляем контрольную сумму
        $binWithChecksum = $bin . $checksum;

        // Base58 кодирование
        return $this->base58Encode($binWithChecksum);
    }

    /**
     * Декодирует Base58Check строку обратно в hex
     *
     * @param string $base58 Base58Check строка
     * @param bool $verifyChecksum Проверять контрольную сумму
     * @return array{hex: string, version: string|null, payload: string}
     * @throws \RuntimeException Если контрольная сумма не совпадает
     */
    protected function base58CheckDecode(string $base58, bool $verifyChecksum = true): array
    {
        // Base58 декодирование
        $bin = $this->base58Decode($base58);

        // Отделяем данные от контрольной суммы
        $payload = substr($bin, 0, -4);
        $checksum = substr($bin, -4);

        // Проверяем контрольную сумму
        if ($verifyChecksum && $checksum !== $this->doubleSha256($payload, 4)) {
            throw new \RuntimeException('Invalid Base58Check checksum');
        }

        // Конвертируем в hex
        $hex = bin2hex($payload);

        // Определяем версию (первые 2 символа hex = 1 байт)
        $version = substr($hex, 0, 2);
        $payloadHex = substr($hex, 2);

        return [
            'hex' => $hex,
            'version' => $version,
            'payload' => $payloadHex,
        ];
    }

    /**
     * Кодирует бинарные данные в Base58
     */
    protected function base58Encode(string $binary): string
    {
        $base58 = new Base58();
        return $base58->encode($binary);
    }

    /**
     * Декодирует Base58 строку в бинарные данные
     */
    protected function base58Decode(string $base58): string
    {
        $base58 = new Base58();
        return $base58->decode($base58);
    }

    /**
     * Вычисляет двойной SHA256 хеш
     *
     * @param string $data Бинарные данные
     * @param int $length Длина возвращаемого хеша (0 = полный хеш)
     * @return string
     */
    protected function doubleSha256(string $data, int $length = 0): string
    {
        $hash1 = hash('sha256', $data, true);
        $hash2 = hash('sha256', $hash1, true);

        if ($length > 0) {
            return substr($hash2, 0, $length);
        }

        return $hash2;
    }

    /**
     * Проверяет, является ли строка валидным Base58Check адресом
     */
    protected function isValidBase58Check(string $address): bool
    {
        try {
            $this->base58CheckDecode($address);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Конвертирует hex в Base58 без контрольной суммы
     */
    protected function hexToBase58(string $hex): string
    {
        return $this->base58Encode(hex2bin($hex));
    }

    /**
     * Конвертирует Base58 в hex
     */
    protected function base58ToHex(string $base58): string
    {
        return bin2hex($this->base58Decode($base58));
    }
}
