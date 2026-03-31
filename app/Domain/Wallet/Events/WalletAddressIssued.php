<?php

namespace App\Domain\Wallet\Events;

final readonly class WalletAddressIssued
{
    public function __construct(
        public int $walletId,
        public string $address,
        public int $derivationIndex,
    ) {}
}
