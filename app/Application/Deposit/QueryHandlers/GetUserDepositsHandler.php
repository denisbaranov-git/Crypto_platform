<?php

namespace App\Application\Deposit\QueryHandlers;

use App\Application\Deposit\Queries\GetUserDepositsQuery;
use App\Application\Deposit\Queries\DTO\PaginatedDepositsDTO;
use App\Application\Deposit\ReadModels\DepositViewRepository;

final readonly class GetUserDepositsHandler
{
    public function __construct(
        private DepositViewRepository $repository,
    ) {
    }

    public function handle( GetUserDepositsQuery $query ): PaginatedDepositsDTO
    {
        return $this->repository->getUserDeposits(
            userId: $query->userId,
            status: $query->status,
            currency: $query->currency,
            network: $query->network,
            page: $query->page,
            perPage: $query->perPage,
        );
    }
}
