<?php

namespace App\Domain\Wallet\Events;

final readonly class WalletArchived
{
    public function __construct(
        public int $walletId
    ) {}
}
