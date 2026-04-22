<?php

namespace App\Domain\Withdrawal\Events;

class WithdrawalReorged
{
    public function __construct(
        public readonly ?int $withdrawalId,
        public readonly string $reason,
        public readonly int $reorgBlockNumber,
    ) {}
}
