<?php

namespace App\Application\Deposit\Queries;

final readonly class GetUserDepositsQuery
{
    public function __construct(
        public int $userId,
        public ?string $status = null,
        public ?string $currency = null,
        public ?string $network = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}
