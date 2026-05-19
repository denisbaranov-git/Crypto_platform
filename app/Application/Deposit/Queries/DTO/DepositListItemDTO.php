<?php

namespace App\Application\Deposit\Queries\DTO;

final readonly class DepositListItemDTO
{
    public function __construct(
        public int $id,
        public string $fromAddress,
        public string $status,
        public string $amount,
        public int $confirmations,
        public int $requiredConfirmations,
        public string $currency,
        public string $network,
        public string $txid,
        public string $createdAt,
    ) {}
}
