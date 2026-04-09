<?php

namespace App\Domain\Ledger\Contracts;

interface LedgerService
{
    public function postDepositCredit(
        int $userId,
        int $currencyId,
        string $amount,
        string $operationId,
        array $metadata = []
    ): void;

    /**
     * На будущее: если deposit был credited, а потом сеть откатила блок,
     * ledger должен уметь делать reversal/adjustment.
     */
    public function reverseDepositCredit(
        string $operationId,
        array $metadata = []
    ): void;
}
