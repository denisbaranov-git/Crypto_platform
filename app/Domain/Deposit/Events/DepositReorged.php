<?php

namespace App\Domain\Deposit\Events;

final readonly class DepositReorged
{
    public function __construct(
        public string $depositKey,
        public int $networkId,
        public string $txHash,
        public ?int $oldBlockNumber,
        public ?string $oldBlockHash,
    ) {}
}
