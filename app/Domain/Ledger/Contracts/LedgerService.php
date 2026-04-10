<?php

namespace App\Domain\Ledger\Contracts;

interface LedgerService
{
    public function postDepositCredit(
        int $depositId,
        int $userId,
        int $currencyId,
        string $amount,
        string $operationId,
        array $metadata = []
    ): void;

    public function reverseDepositCredit(
        int $depositId,
        string $operationId,
        array $metadata = []
    ): void;
}
