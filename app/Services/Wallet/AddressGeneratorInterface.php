<?php

namespace App\Services\Wallet;

use App\Models\Network;

interface AddressGeneratorInterface
{
    public function generate(Network $network, int $index): array;
}
