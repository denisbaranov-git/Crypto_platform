<?php

declare(strict_types=1);

namespace App\Services\Wallet\Traits;

use StephenHill\Base58;

trait Base58CheckTrait
{
    /**
     * Base58Check кодирование (двойной SHA256)
     * Используется в Bitcoin, TRON и других сетях
     *
     * @param string $hex Данные в hex формате
     * @return string Base58Check закодированная строка
     */
    protected function base58checkEncode(string $hex): string
    {
        // 1. Конвертируем hex в бинарные данные
        $bin = hex2bin($hex);

        // 2. Двойной SHA256 для контрольной суммы
        $hash1 = hash('sha256', $bin, true);
        $hash2 = hash('sha256', $hash1, true);

        // 3. Первые 4 байта - контрольная сумма
        $checksum = substr($hash2, 0, 4);

        // 4. Добавляем контрольную сумму к исходным данным
        $binWithChecksum = $bin . $checksum;

        // 5. Base58 кодирование
        $base58 = new Base58();
        return $base58->encode($binWithChecksum);
    }

    /**
     * Декодирование Base58Check
     *
     * @param string $base58String Base58Check закодированная строка
     * @return string Hex строка без контрольной суммы
     * @throws \Exception
     */
    protected function base58checkDecode(string $base58String): string
    {
        $base58 = new Base58();
        $binary = $base58->decode($base58String);

        // Проверяем минимальную длину (1 байт данных + 4 байта checksum)
        if (strlen($binary) < 5) {
            throw new \Exception('Invalid Base58Check string');
        }

        // Отделяем данные и контрольную сумму
        $data = substr($binary, 0, -4);
        $checksum = substr($binary, -4);

        // Проверяем контрольную сумму
        $hash1 = hash('sha256', $data, true);
        $hash2 = hash('sha256', $hash1, true);
        $calculatedChecksum = substr($hash2, 0, 4);

        if ($checksum !== $calculatedChecksum) {
            throw new \Exception('Invalid Base58Check checksum');
        }

        return bin2hex($data);
    }
}
