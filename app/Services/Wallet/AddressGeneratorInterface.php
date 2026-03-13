<?php

namespace App\Services\Wallet;

interface AddressGeneratorInterface
{
    public function generate(string $network): array;
}
