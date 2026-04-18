<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Commands;

final readonly class ReleaseWithdrawalFundsCommand
{
    public function __construct(
        public int $withdrawalId,
        public int $holdId,
        public string $operationId,
        public array $metadata = [],
    ) {}
}
