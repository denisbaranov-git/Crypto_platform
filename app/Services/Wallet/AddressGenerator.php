<?php

namespace App\Services\Wallet;

use App\Models\Network;
use App\Services\Wallet\Generators\EthereumAddressGenerator;
use App\Services\Wallet\Generators\TronAddressGenerator;
use App\Services\Wallet\Generators\BitcoinAddressGenerator;

class AddressGenerator implements AddressGeneratorInterface
{
    public function generate(Network $network, int $index): array
    {
        return match ($network->rpc_driver) {

            'ethereum' => (new EthereumAddressGenerator())->generate($index),

            'tron' => (new TronAddressGenerator())->generate($index),

            'bitcoin' => (new BitcoinAddressGenerator())->generate($index),

            default => throw new \Exception('Unsupported network'),
        };
    }
}
