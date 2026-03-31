<?php

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Wallet\Entities\Wallet;
use App\Domain\Wallet\Entities\WalletAddress;
use App\Domain\Wallet\ValueObjects\CurrencyNetworkId;
use App\Domain\Wallet\ValueObjects\DerivationPath;
use App\Domain\Wallet\ValueObjects\WalletAddressValue;
use App\Domain\Wallet\ValueObjects\WalletId;
use App\Domain\Wallet\ValueObjects\WalletStatus;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentWallet;

class WalletMapper
{
    public function toDomain($model, $addresses): Wallet
    {
        $domainAddresses = [];

        foreach ($addresses as $address) {
            $domainAddresses[] = WalletAddress::create(
                WalletAddressValue::fromString($address->address),
                $address->derivation_index,
                DerivationPath::fromString($address->derivation_path)
            );
        }

        return Wallet::hydrate(
            WalletId::fromInt($model->id),
            UserId::fromInt($model->user_id),
            CurrencyNetworkId::fromInt($model->currency_network_id),
            WalletStatus::from($model->status),
            null,
            $domainAddresses
        );
    }

    public function toModel(Wallet $wallet, ?EloquentWallet $model = null): EloquentWallet
    {
        $model = $model ?? new EloquentWallet();

        if ($wallet->id()) {
            $model->id = $wallet->id()->value();
        }

        $model->user_id = $wallet->userId()->value();
        $model->currency_network_id = $wallet->currencyNetworkId()->value();
        $model->status = $wallet->status()->value;
        $model->active_address_id = $wallet->activeAddressId()?->value();

        return $model;
    }
}
