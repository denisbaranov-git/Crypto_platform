<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Events;

final readonly class WithdrawalAttemptStarted
{
    public function __construct(
        public int $withdrawalId,
        public int $attemptNo,
        public ?string $broadcastDriver,
    ) {}
}
