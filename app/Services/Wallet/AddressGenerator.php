<?php

namespace App\Services\Wallet;

use App\Models\Network;
use App\Services\Wallet\Generators\EthereumAddressGenerator;
use App\Services\Wallet\Generators\TronAddressGenerator;
use App\Services\Wallet\Generators\BitcoinAddressGenerator;
use Illuminate\Support\Facades\Crypt;

class AddressGenerator implements AddressGeneratorInterface
{
    public function generate(string $network): array
    {

        return match ($network) {

            'ethereum' => (new EthereumAddressGenerator())->generate(),

            'tron' => (new TronAddressGenerator())->generate(),

            'bitcoin' => (new BitcoinAddressGenerator())->generate(),

            default => throw new \Exception('Unsupported network'),
        };
    }
}
