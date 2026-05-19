<?php

namespace App\Application\Deposit\QueryHandlers;

use App\Application\Deposit\Queries\DTO\DepositDetailsDTO;
use App\Application\Deposit\Queries\GetDepositDetailsQuery;
use App\Application\Deposit\ReadModels\DepositViewRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class GetDepositDetailsHandler
{
    public function __construct(
        private DepositViewRepository $repository,
    ) {}

    public function handle(GetDepositDetailsQuery $query): DepositDetailsDTO
    {
        $deposit = $this->repository->getDepositDetails(
            userId: $query->userId,
            depositId: $query->depositId,
        );

        if (!$deposit) {
            throw new NotFoundHttpException();
        }

        return $deposit;
    }
}
