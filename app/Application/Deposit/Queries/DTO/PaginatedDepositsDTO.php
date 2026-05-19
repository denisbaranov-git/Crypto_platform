<?php

namespace App\Application\Deposit\Queries\DTO;

final readonly class PaginatedDepositsDTO
{
    /**
     * @param DepositListItemDTO[] $items
     */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
    ) {}
}
