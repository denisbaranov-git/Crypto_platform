<?php

namespace App\Domain\Withdrawal\Events;

class WithdrawalDebited
{
    public function __construct(
        public readonly ?int $withdrawalId,
        public readonly int $networkId,
        public readonly string $externalKey,
        public readonly string $operationId,
    ){}

}
