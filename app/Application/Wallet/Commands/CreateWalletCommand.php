<?php

namespace App\Application\Wallet\Commands;

class CreateWalletCommand
{
    public function __construct(
        public int $userId,
        public int $currencyNetworkId
    ) {}
}
