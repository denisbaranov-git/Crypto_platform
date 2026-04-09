<?php

namespace App\Application\Deposit\Commands;

final readonly class CreditDepositCommand
{
    public function __construct(
        public int $depositId,
        public string $operationId,
        public array $metadata = [],
    ) {}
}
