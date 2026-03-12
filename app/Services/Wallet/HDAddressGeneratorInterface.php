<?php

namespace App\Services\Wallet;

use App\Models\Network;

interface HDAddressGeneratorInterface
{
    public function generate(Network $network, int $index): string;
}
