<?php

//namespace App\Infrastructure\Deposit;
namespace App\Infrastructure\Persistence\Eloquent\Deposit\Repositories;

use App\Domain\Deposit\Services\DepositUniquenessChecker;
use App\Domain\Shared\ValueObjects\ExternalKey;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentDeposit;

final class EloquentDepositUniquenessChecker implements DepositUniquenessChecker
{
    public function exists(int $networkId, ExternalKey $externalKey): bool
    {
        return EloquentDeposit::query()
            ->where('network_id', $networkId)
            ->where('external_key', $externalKey->value())
            ->exists();
    }
}
