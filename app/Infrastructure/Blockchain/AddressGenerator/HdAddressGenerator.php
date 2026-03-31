<?php

namespace App\Infrastructure\Blockchain\AddressGenerator;

use App\Domain\Wallet\ValueObjects\DerivationIndex;
use App\Domain\Wallet\Services\GeneratedAddress;
use App\Domain\Wallet\Services\HdAddressGeneratorInterface;
use App\Domain\Wallet\ValueObjects\NetworkCode;
use App\Domain\Wallet\ValueObjects\XPub;

final class HdAddressGenerator implements HdAddressGeneratorInterface
{
    public function generate(
        NetworkCode $network,
        XPub $xpub,
        int $index
    ): GeneratedAddress {

        return match ($network->value()) {

            'ethereum' => (new EthereumHDAddressGenerator($xpub))
                ->generate($index),

            'tron' => (new TronHDAddressGenerator($xpub))
                ->generate($index),

            'bitcoin' => (new BitcoinHDAddressGenerator($xpub))
                ->generate($index),

            default => throw new \DomainException('Unsupported network'),
        };
    }
}
