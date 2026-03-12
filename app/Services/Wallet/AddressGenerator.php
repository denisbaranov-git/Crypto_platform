<?php

namespace App\Services\Wallet;

use App\Models\Network;
use App\Services\Wallet\Generators\EthereumAddressGenerator;
use App\Services\Wallet\Generators\TronAddressGenerator;
use App\Services\Wallet\Generators\BitcoinAddressGenerator;
use Illuminate\Support\Facades\Crypt;

class AddressGenerator implements AddressGeneratorInterface
{
    public function generate(Network $network): array
    {
        $xpub = config("wallet.{$network->rpc_driver}_xpub", null);
        if(!$xpub) throw new \Exception('xpub address not defined');//null,false,0,'' etc

        return match ($network->rpc_driver) {

            'ethereum' => (new EthereumAddressGenerator())->generate(),

            'tron' => (new TronAddressGenerator())->generate(),

            'bitcoin' => (new BitcoinAddressGenerator())->generate(),

            default => throw new \Exception('Unsupported network'),
        };
    }
}
