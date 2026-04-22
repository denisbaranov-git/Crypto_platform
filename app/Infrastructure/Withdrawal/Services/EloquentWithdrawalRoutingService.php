<?php

declare(strict_types=1);

namespace App\Infrastructure\Withdrawal\Services;

use App\Domain\Withdrawal\Entities\Withdrawal;
use App\Domain\Withdrawal\Services\WithdrawalRoutingService;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentNetwork;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentSystemWallet;
use DomainException;

final class EloquentWithdrawalRoutingService implements WithdrawalRoutingService
{
    public function selectSystemWallet(Withdrawal $withdrawal): int
    {
        $wallet = EloquentSystemWallet::query()
            ->where('network_id', $withdrawal->networkId())
            ->where('status', 'active')
            ->where('type', 'hot')
            ->orderBy('id')
            ->first();

        if (! $wallet) {
            throw new DomainException('No active hot system wallet found.');
        }

        return (int) $wallet->id;
    }

    public function driverForWithdrawal(Withdrawal $withdrawal): string
    {
        $network = EloquentNetwork::query()
            ->findOrFail($withdrawal->networkId());

        return (string) $network->rpc_driver;
    }
}
