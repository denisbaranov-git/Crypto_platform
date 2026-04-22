<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Contracts;

interface LedgerService
{
    public function postDepositCredit(
        int $depositId,
        string $operationId,
        array $metadata = []
    ): void;
    public function reverseDepositCredit(
        int $depositId,
        array $metadata = []
    ): void;

    public function reserveFunds(
        int $userId,
        int $currencyNetworkId,
        string $amount,
        string $operationId,
        string $referenceType,
        ?int $referenceId = null,
        array $metadata = [],
        ?int $expiresInSeconds = null
    ): void;

    public function releaseFunds(
        int $holdId,
        string $operationId,
        string $referenceType,
        ?int $referenceId = null,
        array $metadata = []
    ): void;

    public function consumeHold(
        int $holdId,
        string $operationId,
        string $referenceType,
        ?int $referenceId = null,
        array $metadata = []
    ): void;

    public function transferInternal(
        int $fromUserId,
        int $toUserId,
        int $currencyNetworkId,
        string $amount,
        string $operationId,
        string $referenceType,
        ?int $referenceId = null,
        array $metadata = []
    ): void;

    public function moveToSuspense(
        int $userId,
        int $currencyNetworkId,
        string $amount,
        string $operationId,
        string $reason,
        ?int $referenceId = null,
        array $metadata = []
    ): void;

    public function releaseFromSuspense(
        int $userId,
        int $currencyNetworkId,
        string $amount,
        string $operationId,
        string $reason,
        ?int $referenceId = null,
        array $metadata = []
    ): void;
}
