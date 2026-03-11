<?php

namespace App\Services\Wallet;

use App\Models\Network;

interface AddressGeneratorInterface
{
    //return
    //[
    //'address' => '',
    //'private_key' => '',
    //'public_key' => '',
    //]
    public function generate(Network $network, int $index): array;
}
