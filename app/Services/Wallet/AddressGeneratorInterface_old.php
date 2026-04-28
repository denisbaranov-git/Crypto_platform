<?php

namespace App\Services\Wallet;

interface AddressGeneratorInterfaceOld
{
    public function generate(string $network): array;
}
