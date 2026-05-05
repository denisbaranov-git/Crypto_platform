<?php

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\ValueObjects\DerivationIndex;
use App\Domain\Wallet\ValueObjects\NetworkCode;
use App\Domain\Wallet\ValueObjects\XPub;

interface HDAddressGeneratorInterface
{
//    public function generate(
//        NetworkCode $network,
//        XPub $xpub,
//        int $index
//    ): GeneratedAddress;

    public function generate(string $network, int $index): array|string;
    public function generateMultiple(string $network, int $startIndex, int $count): array;
}
