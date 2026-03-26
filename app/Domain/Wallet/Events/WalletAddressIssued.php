<?php

namespace App\Domain\Wallet\Events;

final readonly class WalletAddressIssued
{
    public function __construct(
        public readonly string $walletId,
        public readonly string $address,
        public readonly int $derivationIndex,
    ) {}
}
