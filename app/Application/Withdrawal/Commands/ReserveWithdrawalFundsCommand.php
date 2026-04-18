<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Commands;

final readonly class ReserveWithdrawalFundsCommand
{
    public function __construct(
        public int $withdrawalId,
        public string $operationId,
        public int $userId,
        public int $currencyNetworkId,
        public string $amount,
        public array $metadata = [],
        public ?int $expiresInSeconds = null,
    ) {}
}
