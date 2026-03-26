<?php

namespace App\Domain\Wallet\Repositories;


use App\Domain\Wallet\Entities\HdWallet;

interface HdWalletRepository
{
    public function lockForNetwork(NetworkId $networkId): HdWallet;

    public function save(HdWallet $hdWallet): void;
}
