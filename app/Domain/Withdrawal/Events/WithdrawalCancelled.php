<?php

namespace App\Domain\Withdrawal\Events;

final readonly class WithdrawalCancelled
{
    public function __construct(
        public int $withdrawalId,
        public string $reason,
    ) {}
}
