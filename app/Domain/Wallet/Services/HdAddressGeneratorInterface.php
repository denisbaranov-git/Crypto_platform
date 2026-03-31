<?php

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\ValueObjects\DerivationIndex;
use App\Domain\Wallet\ValueObjects\NetworkCode;
use App\Domain\Wallet\ValueObjects\XPub;

interface HdAddressGeneratorInterface
{
    public function generate(
        NetworkCode $network,
        XPub $xpub,
        int $index
    ): GeneratedAddress;
}
