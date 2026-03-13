<?php

namespace App\Services\Wallet;

interface HDAddressGeneratorInterface
{
    public function generate(string $network, int $index): string;
}
