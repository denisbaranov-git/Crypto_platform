<?php

namespace App\Domain\Wallet\Events;

final readonly class WalletLocked
{
    public function __construct(
        public readonly string $walletId
    ) {}
}
