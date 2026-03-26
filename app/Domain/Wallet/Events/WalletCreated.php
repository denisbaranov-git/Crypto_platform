<?php

namespace App\Domain\Wallet\Events;

final readonly class WalletCreated
{
    public function __construct(
        public readonly string $walletId,
        public readonly string $userId,
        public readonly string $currencyNetworkId,
    ) {}
}
