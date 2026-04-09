<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Ledger\Repositories;

use App\Domain\Ledger\Entities\Account;
use App\Domain\Ledger\Repositories\AccountRepository;
use App\Infrastructure\Persistence\Eloquent\Mappers\AccountMapper;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentAccount;
use Illuminate\Support\Facades\DB;

/**
 * EloquentAccountRepository
 *
 * Реализация repository contract для MySQL + Eloquent.
 *
 * Почему это Infrastructure:
 * - потому что тут есть ORM;
 * - тут есть SQL locking;
 * - тут есть знание о структуре таблицы;
 * - Domain и Application не должны знать этих деталей.
 */
final class EloquentAccountRepository implements AccountRepository
{
    public function findByOwnerAndCurrencyNetwork(
        string $ownerType,
        int $ownerId,
        int $currencyNetworkId
    ): ?Account {
        $model = EloquentAccount::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('currency_network_id', $currencyNetworkId)
            ->first();

        return $model ? AccountMapper::toDomain($model) : null;
    }

    public function getByIdForUpdate(int $id): ?Account
    {
        $model = EloquentAccount::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->first();

        return $model ? AccountMapper::toDomain($model) : null;
    }

    public function save(Account $account): Account
    {
        return DB::transaction(function () use ($account) {
            $model = null;

            if ($account->id() !== null) {
                $model = EloquentAccount::query()->whereKey($account->id())->first();
            }

            if ($model === null) {
                $model = new EloquentAccount();
            }

            AccountMapper::toModel($model, $account);
            $model->save();

            return AccountMapper::toDomain($model);
        });
    }
}
