<?php

namespace App\Services\Wallet;

interface AddressGeneratorInterface
{
    /**
     * Генерирует новую пару ключей и возвращает адрес и приватный ключ.
     * @param array $options Дополнительные параметры (тип адреса для Bitcoin и т.д.)
     * @return array ['address' => string, 'private_key' => string]
     */
    public function generate(string $network): array;

    /**
     * Восстанавливает адрес из приватного ключа.
     * @param string $privateKey
     * @return string
     */
    //public function addressFromPrivateKey(string $privateKey): string;
}
