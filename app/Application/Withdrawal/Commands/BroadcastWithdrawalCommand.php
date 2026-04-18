<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Commands;

final readonly class BroadcastWithdrawalCommand
{
    public function __construct(
        public int $withdrawalId,
        public array $metadata = [],
    ) {}
}
