<?php

namespace App\Domain\Withdrawal\Events;

final readonly class WithdrawalSettled
{
    public function __construct(
        public int $withdrawalId,
        public string $consumeOperationId,
    ) {}
}
