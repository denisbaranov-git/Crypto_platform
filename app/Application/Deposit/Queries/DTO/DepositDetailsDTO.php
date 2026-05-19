<?php

namespace App\Application\Deposit\Queries\DTO;

final readonly class DepositDetailsDTO
{
    public function __construct(
        public int $id,
        public string $status,
        public string $amount,
        public int $confirmations,
        public int $requiredConfirmations,
        public string $txid,
        public ?string $fromAddress,
        public string $toAddress,
        public ?string $blockHash,
        public ?int $blockNumber,
        public string $currency,
        public string $network,
        public string $walletAddress,
        public ?string $creditedAt,
        public string $createdAt,
        public ?string $explorerUrl,
    ) { }
}
