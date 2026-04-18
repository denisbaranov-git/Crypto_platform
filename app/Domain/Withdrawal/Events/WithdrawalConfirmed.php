<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Events;

final readonly class WithdrawalConfirmed
{
    public function __construct(
        public int $withdrawalId,
        public int $networkId,
        public string $externalKey,
        public string $txid,
    ) {}
}
