<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Wallet\Entities\HdWallet;
use App\Domain\Wallet\Repositories\HdWalletRepository;
use App\Domain\Wallet\ValueObjects\NetworkId;
use App\Infrastructure\Persistence\Eloquent\Mappers\HdWalletMapper;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentHdWallet;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class EloquentHdWalletRepository implements HdWalletRepository
{
    public function __construct(
        private HdWalletMapper $mapper,
    ) {}

    public function lockForNetwork(NetworkId $networkId): HdWallet
    {
        $model = EloquentHdWallet::where('network_id', $networkId->value())
            ->lockForUpdate()
            //->firstOrFail();
            ->first();
        if(!$model) {
            throw new ModelNotFoundException("Network with ID {$networkId->value()} not found in table hd_wallets.");
        }

        return $this->mapper->toDomain($model);
    }

    public function save(HdWallet $hdwallet): void
    {
        DB::transaction(function () use ($hdwallet) {
            $model = EloquentHdWallet::where('network_id', $hdwallet->networkId()->value())->firstOrFail();

            $model->next_index = $hdwallet->nextIndex();
            $model->encrypted_xpub = encrypt($hdwallet->xpub()->value());
            $model->status = $hdwallet->status();
            $model->save();
        });
    }
}
