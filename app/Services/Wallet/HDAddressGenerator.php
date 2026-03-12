<?php

namespace App\Services\Wallet;

use App\Models\Network;
use App\Services\Wallet\Generators\EthereumHDAddressGenerator;
use App\Services\Wallet\Generators\TronHDAddressGenerator;
use App\Services\Wallet\Generators\BitcoinHDAddressGenerator;
use Illuminate\Support\Facades\Crypt;

class HDAddressGenerator implements HDAddressGeneratorInterface
{
    public function generate(Network $network, int $index): string
    {
        $xpub = config("wallet.{$network->rpc_driver}_xpub", null);
        if(!$xpub) throw new \Exception('xpub address not defined');//null,false,0,'' etc

        return match ($network->rpc_driver) {

            'ethereum' => (new EthereumHDAddressGenerator($xpub))->generate($index),

            'tron' => (new TronHDAddressGenerator($xpub))->generate($index),

            'bitcoin' => (new BitcoinHDAddressGenerator($xpub))->generate($index),

            default => throw new \Exception('Unsupported network'),
        };
    }
}
