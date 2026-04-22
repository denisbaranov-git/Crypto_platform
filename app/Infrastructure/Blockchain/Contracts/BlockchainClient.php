<?php

declare(strict_types=1);

namespace App\Infrastructure\Blockchain\Contracts;

use App\Application\Deposit\DTO\DetectedBlockchainEvent;
use App\Domain\Withdrawal\Entities\Withdrawal;
use App\Infrastructure\Blockchain\DTO\BlockchainTransactionStatus;
use App\Infrastructure\Blockchain\DTO\PreparedWithdrawalTransaction;

interface BlockchainClient
{
    public function headBlock(): int;

    public function blockHash(int $blockNumber): string;

    /**
     * Deposit scanning.
     *
     * @param array<int, mixed> $tokenContracts
     * @return array<int, DetectedBlockchainEvent>
     */
    public function scanBlock(int $blockNumber, array $tokenContracts = []): array;

    /**
     * Withdrawal confirmation polling.
     */
    public function transaction(string $txid): ?BlockchainTransactionStatus;

    /**
     * Withdrawal preparation:
     * create + sign raw tx using a system hot wallet.
     */
    public function prepareWithdrawal(
        Withdrawal $withdrawal,
        int $systemWalletId,
        array $context = []
    ): PreparedWithdrawalTransaction;

    /**
     * Broadcast pre-signed raw transaction.
     */
    public function broadcastWithdrawalRaw(string $rawTransaction): string;
}
