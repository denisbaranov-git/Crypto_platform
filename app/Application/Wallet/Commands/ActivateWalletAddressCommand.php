<?php

namespace App\Application\Wallet\Commands;

class ActivateWalletAddressCommand
{
    public function __construct(
        public int $walletId
    ){}
}
