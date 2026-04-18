<?php

declare(strict_types=1);

namespace App\Infrastructure\Blockchain\DTO;

final readonly class BlockchainTransactionStatus
{
    public function __construct(
        public string $txid,
        public ?int $blockNumber,
        public ?string $blockHash,
        public int $confirmations,
        public bool $finalized = false,
    ) {}
}
