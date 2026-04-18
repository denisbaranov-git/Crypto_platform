<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Events;

final readonly class WithdrawalBroadcasted
{
    public function __construct(
        public int $withdrawalId,
        public string $txid,
        public int $systemWalletId,
    ) {}
}
