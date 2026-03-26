<?php

namespace App\Domain\Wallet\Repositories;

use App\Domain\Wallet\Entities\WalletAddress;
use App\Domain\Wallet\ValueObjects\WalletId;

interface WalletAddressRepository
{
    public function findByAddress(string $address): ?WalletAddress;

    public function existsByAddress(string $address): bool;

    public function save(WalletAddress $address, WalletId $walletId): void;
}
