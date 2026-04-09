<?php

namespace App\Infrastructure\Blockchain\Contracts;

use App\Application\Deposit\DTO\DetectedBlockchainEvent;
use App\Domain\Deposit\DTO\TokenContractDescriptor;

interface BlockchainClient
{
    public function headBlock(): int;

    public function blockHash(int $blockNumber): string;

    /**
     * @param TokenContractDescriptor[] $tokenContracts
     * @return DetectedBlockchainEvent[]
     */
    public function scanBlock(int $blockNumber, array $tokenContracts = []): array;
}
