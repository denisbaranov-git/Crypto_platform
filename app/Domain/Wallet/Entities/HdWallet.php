<?php

namespace App\Domain\Wallet\Entities;

use App\Domain\Wallet\ValueObjects\DerivationIndex;
use App\Domain\Wallet\ValueObjects\HdWalletId;
use App\Domain\Wallet\ValueObjects\NetworkId;
use App\Domain\Wallet\ValueObjects\XPub;
use DomainException;

final class HdWallet
{
        private ?HdWalletId $id = null;
        private NetworkId $networkId;
        private XPub $xpub;
        private  int $nextIndex;

    public function __construct(NetworkId $networkId, XPub $xpub, int $nextIndex = 0 ) {
        $this->networkId = $networkId;
        $this->xpub = $xpub;
        $this->nextIndex = $nextIndex;
    }

    public static function hydrate(HdWalletId $id, NetworkId $networkId, XPub $xpub, int $nextIndex): self
    {
        $hd_wallet = new self($networkId, $xpub, $nextIndex);
        $hd_wallet->id = $id;

        return $hd_wallet;
    }
    public function incrementNextIndex(): void
    {
//        if ($this->status !== 'active') {
//            throw new DomainException('HD wallet is not active.');
//        }

        //$index = $this->nextIndex;
        $this->nextIndex++;
        //return $index;
    }

    public function id(): int
    {
        return $this->id->value();
    }

    public function networkId(): int
    {
        return $this->networkId->value();
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
