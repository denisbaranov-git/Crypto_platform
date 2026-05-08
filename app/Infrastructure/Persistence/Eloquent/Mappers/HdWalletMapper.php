<?php

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Wallet\Entities\HdWallet;
use App\Domain\Wallet\Entities\Wallet;
use App\Domain\Wallet\Entities\WalletAddress;
use App\Domain\Wallet\ValueObjects\CurrencyNetworkId;
use App\Domain\Wallet\ValueObjects\NetworkId;
use App\Domain\Wallet\ValueObjects\XPub;
use App\Domain\Wallet\ValueObjects\DerivationPath;
use App\Domain\Wallet\ValueObjects\HdWalletId;
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
            HdWalletId::fromInt($model->id),
            NetworkId::fromInt( $model->network_id),
            XPub::fromString($model->xpub),
            $model->next_index
        );
    }

    public function toModel(HdWallet $hdWallet, ?EloquentHdWallet $model = null): EloquentHdWallet
    {
        $model = $model ?? new EloquentHdWallet();

        if ($hdWallet->id()) {
            $model->id = $hdWallet->id();
        }
        $model->network_id = $hdWallet->networkId();
        $model->xpub = $hdWallet->xpub()->value();
        $model->next_index = $hdWallet->nextIndex();

        return $model;
    }
}
