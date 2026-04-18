<?php

namespace App\Domain\Withdrawal\Events;

final readonly class WithdrawalReleased
{
    public function __construct(
        public int $withdrawalId,
        public string $releaseOperationId,
    ) {}
}

