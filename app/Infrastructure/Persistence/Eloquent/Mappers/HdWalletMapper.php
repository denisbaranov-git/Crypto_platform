<?php

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Wallet\Entities\HdWallet;
use App\Domain\Wallet\Entities\Wallet;
use App\Domain\Wallet\Entities\WalletAddress;
use App\Domain\Wallet\ValueObjects\CurrencyNetworkId;
use App\Domain\Wallet\ValueObjects\DerivationIndex;
use App\Domain\Wallet\ValueObjects\DerivationPath;
use App\Domain\Wallet\ValueObjects\WalletAddressValue;
use App\Domain\Wallet\ValueObjects\WalletId;
use App\Domain\Wallet\ValueObjects\WalletStatus;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentHdWallet;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentWallet;

class HdWalletMapper
{
    public function toDomain($model): HdWallet
    {
        return HdWallet::hydrate(
            WalletId::fromInt($model->id),
            UserId::fromInt($model->user_id),
            CurrencyNetworkId::fromInt($model->currency_network_id)
        );
    }

    public function toModel(HdWallet $hdWallet, ?EloquentHdWallet $model = null): EloquentHdWallet
    {
        $model = $model ?? new EloquentHdWallet();

        if ($hdWallet->id()) {
            $model->id = $hdWallet->id()->value();
        }

        $model->user_id = $hdWallet->userId()->value();
        $model->currency_network_id = $hdWallet->currencyNetworkId()->value();
        $model->status = $hdWallet->status()->value;
        $model->active_address_id = $hdWallet->activeAddressId()?->value();

        return $model;
    }
}
