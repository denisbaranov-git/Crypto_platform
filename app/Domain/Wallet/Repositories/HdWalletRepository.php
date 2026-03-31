<?php

namespace App\Domain\Wallet\Repositories;


use App\Domain\Wallet\Entities\HdWallet;
use App\Domain\Wallet\ValueObjects\NetworkId;

interface HdWalletRepository
{
    public function lockForNetwork(NetworkId $networkId): HdWallet;

    public function save(HdWallet $hdWallet): void;
}
