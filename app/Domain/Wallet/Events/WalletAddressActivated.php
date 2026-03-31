<?php

namespace App\Domain\Wallet\Events;

final class WalletAddressActivated
{
    public function __construct(
        public int $walletId,
        public int $addressId,
    ) {}
}
