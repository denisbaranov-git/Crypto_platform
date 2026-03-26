<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Wallet\Entities\Wallet;
use App\Domain\Wallet\ValueObjects\CurrencyNetworkId;
use App\Domain\Wallet\ValueObjects\WalletAddressId;
use App\Domain\Wallet\ValueObjects\WalletId;
use App\Domain\Wallet\ValueObjects\WalletStatus;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentWallet;

class WalletMapper
{
    public function toDomain(EloquentWallet $model): Wallet
    {
        return Wallet::hydrate(
            id: WalletId::fromInt($model->id),
            userId: UserId::fromInt($model->user_id),
            currencyNetworkId: CurrencyNetworkId::fromInt($model->currency_network_id),
            status: WalletStatus::from($model->status),
            activeAddressId: WalletAddressId::fromInt($model->active_address_id),
            addresses: $model->addresses->pluck('id')->toArray()
        );
    }

    public function toModel(Wallet $wallet, ?EloquentWallet $model = null): EloquentWallet
    {
        //$model = $user->id()? EloquentUser::findOrFail($user->id()->value()) : new EloquentUser();
        $model = $model ?? new EloquentWallet();
        $model->user_id = $wallet->userId()->value();
        $model->currency_network_id = $wallet->currencyNetworkId()->value();
        $model->status = $wallet->status()->value;
        $model->active_address_id = $wallet->activeAddressId()->value();

        !!!!!refactor this is wrong ->>>>>>> $model->addresses = $wallet->addresses();

        return $model;
    }

}
