<?php

namespace App\Application\Wallet\Commands;

use App\Domain\Wallet\ValueObjects\WalletId;

class LockWalletCommand
{
    public function __construct(
        public int $walletId
    ){}

}
