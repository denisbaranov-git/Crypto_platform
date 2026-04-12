<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Repositories;

use App\Domain\Ledger\Entities\LedgerHold;

interface LedgerHoldRepository
{
    public function findById(int $id): ?LedgerHold;

    public function findByIdForUpdate(int $id): ?LedgerHold;

    public function findActiveByLedgerOperationId(string $ledgerOperationId): ?LedgerHold;

    public function save(LedgerHold $hold): LedgerHold;
}
