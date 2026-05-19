<?php

namespace App\Application\Deposit\ReadModels;

use App\Application\Deposit\Queries\DTO\DepositDetailsDTO;
use App\Application\Deposit\Queries\DTO\PaginatedDepositsDTO;

interface DepositViewRepository
{
    public function getUserDeposits(
        int $userId,
        ?string $status,
        ?string $currency,
        ?string $network,
        int $page,
        int $perPage
    ): PaginatedDepositsDTO;

    public function getDepositDetails(
        int $userId,
        int $depositId
    ): ?DepositDetailsDTO;
}
