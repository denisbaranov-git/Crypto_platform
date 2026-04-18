<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Events;

final readonly class WithdrawalAttemptBroadcasted
{
    public function __construct(
        public int $withdrawalId,
        public int $attemptNo,
        public string $txid,
        public ?string $broadcastDriver = null,
    ) {}
}
