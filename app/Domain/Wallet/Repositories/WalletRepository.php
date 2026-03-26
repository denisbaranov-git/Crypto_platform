<?php

namespace App\Domain\Wallet\Repositories;

use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Wallet\Entities\Wallet;
use App\Domain\Wallet\ValueObjects\CurrencyNetworkId;
use App\Domain\Wallet\ValueObjects\WalletId;

interface WalletRepository
{
    public function save(Wallet $wallet): void;

    public function findById(WalletId $id): ?Wallet;

    public function getByUserAndCurrencyNetwork(
        UserId $userId,
        CurrencyNetworkId $currencyNetworkId
    ): ?Wallet;

    public function existsByUserAndCurrencyNetwork(
        UserId $userId,
        CurrencyNetworkId $currencyNetworkId
    ): bool;
}
