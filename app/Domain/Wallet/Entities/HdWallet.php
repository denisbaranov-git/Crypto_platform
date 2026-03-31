<?php

namespace App\Domain\Wallet\Entities;

use App\Domain\Wallet\ValueObjects\DerivationIndex;
use App\Domain\Wallet\ValueObjects\HdWalletId;
use App\Domain\Wallet\ValueObjects\NetworkId;
use App\Domain\Wallet\ValueObjects\XPub;
use DomainException;

final class HdWallet
{
    public function __construct(
        private readonly HdWalletId $id,
        private readonly NetworkId $networkId,
        private readonly XPub $xpub,
        private  int $nextIndex = 0,
        //private string $status = 'active'
    ) {}

    public function incrementNextIndex(): void
    {
//        if ($this->status !== 'active') {
//            throw new DomainException('HD wallet is not active.');
//        }

        //$index = $this->nextIndex;
        $this->nextIndex++;
        //return $index;
    }

//    public function rewindTo(int $index): void
//    {
//        if ($index < 0) {
//            throw new DomainException('Index cannot be negative.');
//        }
//
//        $this->nextIndex = $index;
//    }

    public function id(): int
    {
        return $this->id;
    }

    public function networkId(): int
    {
        return $this->networkId;
    }

    public function xpub(): XPub
    {
        return $this->xpub;
    }

    public function nextIndex(): int
    {
        return $this->nextIndex;
    }
}
