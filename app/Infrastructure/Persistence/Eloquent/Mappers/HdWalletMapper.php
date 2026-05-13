<?php

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\Wallet\Entities\HdWallet;
use App\Domain\Wallet\ValueObjects\NetworkId;
use App\Domain\Wallet\ValueObjects\XPub;
use App\Domain\Wallet\ValueObjects\HdWalletId;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentHdWallet;

final class HdWalletMapper
{
    public function toDomain(EloquentHdWallet $model): HdWallet
    {
        return HdWallet::hydrate(
            HdWalletId::fromInt($model->id),
            NetworkId::fromInt($model->network_id),
            XPub::fromString($this->decryptXPub($model->encrypted_xpub)),
            (int) $model->next_index,
            $model->status,
        );
    }

    public function toModel(HdWallet $wallet, ?EloquentHdWallet $model = null): EloquentHdWallet
    {
        $model = $model ?? new EloquentHdWallet();

        if ($wallet->id()) {
            $model->id = $wallet->id()->value();
        }

        $model->network_id = $wallet->networkId()->value();
        $model->encrypted_xpub = $this->encryptXPub($wallet->xpub()->value());
        $model->next_index = $wallet->nextIndex();
        $model->status = $wallet->status();

        return $model;
    }

    private function encryptXPub(string $xpub): string
    {
        return encrypt($xpub);
    }

    private function decryptXPub(string $encrypted): string
    {
        return decrypt($encrypted);
    }
}
