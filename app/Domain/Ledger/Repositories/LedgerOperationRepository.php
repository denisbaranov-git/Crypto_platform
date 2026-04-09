<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Repositories;

use App\Domain\Ledger\Entities\LedgerOperation;

interface LedgerOperationRepository
{
    public function findByIdempotencyKey(string $idempotencyKey): ?LedgerOperation;

    public function save(LedgerOperation $operation): LedgerOperation;
}
