<?php

namespace App\Domain\Deposit\Events;

final readonly class DepositConfirmed
{
    public function __construct(
        public string $depositKey,
        public int $networkId,
        public int $userId,
        public string $txHash,
        public int $confirmations,
    ) {}
}
