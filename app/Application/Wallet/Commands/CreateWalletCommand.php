<?php

namespace App\Application\Wallet\Commands;

class CreateWalletCommand
{
    public function __construct(
        public int $userId,
        public int $networkId,
        public string $networkCode,
        public int $currencyNetworkId
    ) {}
}
