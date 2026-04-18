<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Events;

final readonly class WithdrawalReserved
{
    public function __construct(
        public int $withdrawalId,
        public int $ledgerHoldId,
        public string $reserveOperationId,
    ) {}
}
