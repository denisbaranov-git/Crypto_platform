<?php

namespace App\Domain\Wallet\Events;

final readonly class WalletActiveAddressChanged
{
    public function __construct(
        public readonly string $walletId,
        public readonly string $addressId
    ) {}
}
