<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Events;

final readonly class WithdrawalAttemptFailed
{
    public function __construct(
        public int $withdrawalId,
        public int $attemptNo,
        public string $reason,
        public ?string $txid = null,
    ) {}
}
