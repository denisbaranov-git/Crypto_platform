<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Wallet\Entities\HdWallet;
use App\Domain\Wallet\Repositories\HdWalletRepository;
use App\Domain\Wallet\ValueObjects\NetworkId;
use App\Infrastructure\Persistence\Eloquent\Mappers\HdWalletMapper;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentHdWallet;
use Illuminate\Support\Facades\DB;

class EloquentHdWalletRepository implements HdWalletRepository
{
    public function __construct(private HdWalletMapper $mapper) {}

    public function lockForNetwork(NetworkId $networkId): HdWallet
    {
        //return DB::transaction(function () use ($networkId) {
            $hdWallet = EloquentHdWallet::where('network_id', $networkId)
                ->lockForUpdate()
                ->firstOrFail();
            return $this->mapper->toDomain($hdWallet);
        //});
    }

    public function save(HdWallet $hdWallet): void
    {
        // TODO: Implement save() method.
    }
}
