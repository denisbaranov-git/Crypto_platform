<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\Ledger\Entities\Account;
use App\Domain\Ledger\ValueObjects\Money;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentAccount;

/**
 * Mapper нужен для отделения доменной модели от Eloquent model.
 *
 * Почему это важно:
 * - Domain не должен знать про ORM;
 * - Infrastructure знает и domain, и ORM, поэтому перевод живёт тут;
 * - потом можно легко заменить Eloquent на другое хранилище.
 */
final class AccountMapper
{
    public static function toDomain(EloquentAccount $model): Account
    {
        return new Account(
            id: $model->id,
            ownerType: $model->owner_type,
            ownerId: (int) $model->owner_id,
            currencyNetworkId: (int) $model->currency_network_id,
            balance: new Money((string) $model->balance),
            reservedBalance: new Money((string) $model->reserved_balance),
            status: $model->status,
            version: (int) $model->version,
            metadata: $model->metadata ?? [],
        );
    }

    public static function toModel(EloquentAccount $model, Account $account): void
    {
        if ($account->id() !== null) {
            $model->id = $account->id();
        }

        $model->owner_type = $account->ownerType();
        $model->owner_id = $account->ownerId();
        $model->currency_network_id = $account->currencyNetworkId();
        $model->balance = $account->balance()->amount;
        $model->reserved_balance = $account->reservedBalance()->amount;
        $model->status = $account->status();
        $model->version = $account->version();
        $model->metadata = $account->metadata();
    }
}
