<?php

namespace App\Domain\Deposit\Events;

final readonly class DepositDetected
{
    public function __construct(
        public string $depositKey,
        public int $networkId,
        public int $currencyId,
        public int $userId,
        public string $txHash,
        public ?int $logIndex,
        public string $amount,
    ) {}
}
