<?php

namespace App\Domain\Ledger\Entities;

use App\Domain\Ledger\Exceptions\InvalidHoldState;

final class LedgerHold
{
    public function __construct(
        private ?int $id,
        private string $ledgerOperationId,
        private int $accountId,
        private int $currencyNetworkId,
        private string $amount,
        private string $status = 'active',
        private ?string $reason = null,
        private ?string $expiresAt = null,
        private ?string $releasedAt = null,
        private ?string $consumedAt = null,
        private array $metadata = [],
    ) {}

    public function release(): void
    {
        if ($this->status !== 'active') {
            throw new InvalidHoldState('Only active hold can be released.');
        }

        $this->status = 'released';
        $this->releasedAt = now()->toDateTimeString();
    }

    public function consume(): void
    {
        if ($this->status !== 'active') {
            throw new InvalidHoldState('Only active hold can be consumed.');
        }

        $this->status = 'consumed';
        $this->consumedAt = now()->toDateTimeString();
    }

    public function expire(): void
    {
        if ($this->status !== 'active') {
            throw new InvalidHoldState('Only active hold can expire.');
        }

        $this->status = 'expired';
    }
}
