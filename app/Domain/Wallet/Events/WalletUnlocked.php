<?php

namespace App\Domain\Wallet\Events;

final readonly class WalletUnlocked
{
    public function __construct(
        public string $walletId,
        public ?string $reason = null
    ) {}
}
