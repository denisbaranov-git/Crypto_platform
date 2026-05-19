<?php

namespace App\Application\Deposit\Queries;

final readonly class GetDepositDetailsQuery
{
    public function __construct(
        public int $userId,
        public int $depositId,
    ) {}
}
