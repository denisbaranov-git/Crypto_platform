<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Wallet\Entities\Wallet;
use App\Domain\Wallet\Repositories\WalletRepository;
use App\Domain\Wallet\ValueObjects\CurrencyNetworkId;
use App\Domain\Wallet\ValueObjects\WalletId;
use App\Infrastructure\Persistence\Eloquent\Mappers\WalletMapper;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentWallet;
use App\Models\EloquentWalletAddress;
use Illuminate\Support\Facades\DB;

final class EloquentWalletRepository implements WalletRepository
{
    public function __construct(private WalletMapper $mapper) {}

    public function findById(WalletId $id): ?Wallet
    {
        $model = EloquentWallet::findOrFail($id);

        return $this->mapper->toDomain($model, $model->addresses);
    }

    public function getByUserAndCurrencyNetwork(UserId $userId, CurrencyNetworkId $currencyNetworkId): ?Wallet
    {
        $model = EloquentWallet::with('addresses')
            ->where('user_id', $userId->value())
            ->where('currency_network_id', $currencyNetworkId->value())
            ->first();

        if (!$model) return null;

        return $this->mapper->toDomain($model, $model->addresses);
    }

    public function existsByUserAndCurrencyNetwork(UserId $userId, CurrencyNetworkId $currencyNetworkId): bool
    {
        return EloquentWallet::where('user_id', $userId->value())
            ->where('currency_network_id', $currencyNetworkId->value())
            ->exists();
    }

    public function save(Wallet $wallet): void
    {
        DB::transaction(function () use ($wallet) {
            $model = $wallet->id()
                ? EloquentWallet::findOrFail($wallet->id()->value())
                : new EloquentWallet();

            $model = $this->mapper->toModel($wallet, $model);
            $model->save();

            if (!$wallet->id()) {
                $wallet->assignId(WalletId::fromInt($model->id));
            }

            foreach ($wallet->addresses() as $address) {
                $addressModel = $address->id()
                    ? EloquentWalletAddress::find($address->id()->value()) ?? new EloquentWalletAddress()
                    : new EloquentWalletAddress();

                $addressModel->wallet_id = $model->id;
                $addressModel->currency_network_id = $wallet->currencyNetworkId()->value();
                $addressModel->address = $address->address()->value();
                $addressModel->derivation_index = $address->derivationIndex();
                $addressModel->derivation_path = $address->derivationPath()->value();
                $addressModel->status = $address->status();
                $addressModel->is_active = $address->isActive();
                $addressModel->save();
            }
        });
    }
}
