<?php

namespace App\Domain\Withdrawal\Events;

class WithdrawalReorged
{
    public function __construct(
        public readonly ?int $withdrawalId,
        public readonly int $networkId,
        public readonly string $externalKey,
        public readonly ?int $oldBlockNumber = null,
        public readonly ?int $newBlockNumber = null,
    ) {}
}
