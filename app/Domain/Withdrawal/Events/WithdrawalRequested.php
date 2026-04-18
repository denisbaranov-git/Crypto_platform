<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Events;

final readonly class WithdrawalRequested
{
    public function __construct(
        public ?int $withdrawalId,
        public int $userId,
        public int $networkId,
        public int $currencyNetworkId,
        public string $idempotencyKey,
        public string $amount,
    ) {}
}
