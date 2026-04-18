<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Events;

final readonly class WithdrawalFailed
{
    public function __construct(
        public int $withdrawalId,
        public string $reason,
        public ?string $lastError = null,
    ) {}
}
