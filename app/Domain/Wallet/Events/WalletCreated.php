<?php

namespace App\Domain\Wallet\Events;

final readonly class WalletCreated
{
    public function __construct(
        public int $walletId,
        public int $userId,
        public int $currencyNetworkId,
    ) {}
}
