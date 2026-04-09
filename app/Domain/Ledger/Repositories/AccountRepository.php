<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Repositories;

use App\Domain\Ledger\Entities\Account;
use App\Domain\Ledger\ValueObjects\Money;

interface AccountRepository
{
    public function findByOwnerAndCurrencyNetwork(
        string $ownerType,
        int $ownerId,
        int $currencyNetworkId
    ): ?Account;

    /**
     * Получить account под блокировкой FOR UPDATE.
     * Используется в write path, где деньги меняются атомарно.
     */
    public function getByIdForUpdate(int $id): ?Account;

    /**
     * Сохранение aggregate.
     * Репозиторий должен либо создать, либо обновить account.
     */
    public function save(Account $account): Account;
}
