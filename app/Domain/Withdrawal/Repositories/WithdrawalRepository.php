<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Repositories;

use App\Domain\Withdrawal\Entities\Withdrawal;

interface WithdrawalRepository
{
    public function save(Withdrawal $withdrawal): Withdrawal;

    public function byId(int $id): ?Withdrawal;

    public function byIdempotencyKey(string $key): ?Withdrawal;

    public function lockById(int $id): ?Withdrawal;

    /**
     * Open = needs confirmation polling.
     */
    public function findOpenByNetwork(int $networkId, int $limit = 500): array;
}
