<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Commands;

final readonly class CancelWithdrawalCommand
{
    public function __construct(
        public int $withdrawalId,
        public string $reason,
        public array $metadata = [],
    ) {}
}
