<?php

namespace App\Domain\Wallet\Events;

final class WalletActivated
{
    public function __construct(
        public int $walletId,
    ) {}
}
