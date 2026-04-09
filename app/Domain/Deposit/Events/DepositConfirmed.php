<?php

namespace App\Domain\Deposit\Events;

final class DepositConfirmed
{
    public function __construct(
        public readonly ?int $depositId,
        public readonly int $networkId,
        public readonly string $externalKey,
        public readonly string $txid,
    ) {}
}
