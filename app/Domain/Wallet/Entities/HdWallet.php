<?php

namespace App\Domain\Wallet\Entities;

use App\Domain\Wallet\ValueObjects\HdWalletId;
use App\Domain\Wallet\ValueObjects\NetworkId;
use App\Domain\Wallet\ValueObjects\XPub;

final class HdWallet
{
    private ?HdWalletId $id = null;
    private NetworkId $networkId;
    private XPub $xpub;
    private int $nextIndex;
    private string $status;

    private function __construct(
        NetworkId $networkId,
        XPub $xpub,
        int $nextIndex,
        string $status,
    ) {
        $this->networkId = $networkId;
        $this->xpub = $xpub;
        $this->nextIndex = $nextIndex;
        $this->status = $status;
    }

    public static function hydrate(
        HdWalletId $id,
        NetworkId $networkId,
        XPub $xpub,
        int $nextIndex,
        string $status,
    ): self {
        $wallet = new self($networkId, $xpub, $nextIndex, $status);
        $wallet->id = $id;

        return $wallet;
    }

    public function id(): ?HdWalletId
    {
        return $this->id;
    }

    public function networkId(): NetworkId
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

    public function incrementNextIndex(): void
    {
        $this->nextIndex++;
    }

    public function status(): string
    {
        return $this->status;
    }
}
