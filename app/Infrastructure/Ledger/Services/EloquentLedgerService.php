<?php

namespace App\Infrastructure\Ledger\Services;

use App\Domain\Ledger\Contracts\LedgerService;

final class EloquentLedgerService implements LedgerService
{
    // ...
    public function postDepositCredit(int $userId, int $currencyId, string $amount, string $operationId, array $metadata = []): void
    {
        // TODO: Implement postDepositCredit() method.
    }
    public function reverseDepositCredit( string $operationId, array $metadata = [] ): void
    {
        // TODO: Implement reverseDepositCredit() method.
    }}
