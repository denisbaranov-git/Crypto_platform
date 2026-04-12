<?php

declare(strict_types=1);

namespace App\Application\Ledger\Commands;

/**
 * Команда внутреннего перевода.
 *
 * Важно:
 * - это transfer внутри платформы;
 * - on-chain сети тут нет;
 * - одна операция порождает 2 journal entries:
 *   debit sender, credit receiver.
 */
final readonly class TransferFundsCommand
{
    public function __construct(
        public string $idempotencyKey,
        public string $fromOwnerType,
        public int $fromOwnerId,
        public string $toOwnerType,
        public int $toOwnerId,
        public int $currencyNetworkId,
        public string $amount,
        public string $referenceType,
        public ?int $referenceId = null,
        public ?string $description = null,
        public array $metadata = [],
    ) {}
}
