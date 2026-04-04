<?php

namespace App\Application\Wallet\Commands;

class ArchiveWalletCommand
{
    public function __construct(
        public int $walletId
    ){}
}
