<?php

namespace App\Domain\Deposit\Events;

final class DepositReorged
{
    public function __construct(
        public readonly ?int $depositId,
        public readonly int $networkId,
        public readonly string $externalKey,
        public readonly ?int $oldBlockNumber = null,
        public readonly ?int $newBlockNumber = null,
    ) {}
}
