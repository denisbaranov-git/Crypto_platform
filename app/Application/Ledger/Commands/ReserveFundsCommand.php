<?php

declare(strict_types=1);

namespace App\Application\Ledger\Commands;

/**
 * Команда резервирования средств.
 *
 * Обычно создаётся из Withdrawal Requested:
 * - withdrawal:123:reserve
 *
 * Почему отдельная команда:
 * - reserve и consume — это разные шаги lifecycle;
 * - они должны быть идемпотентными по-разному;
 * - reserve не равен final debit.
 */
final readonly class ReserveFundsCommand
{
    public function __construct(
        public string $idempotencyKey,
        public string $ownerType,
        public int $ownerId,
        public int $currencyNetworkId,
        public string $amount,
        public string $referenceType,
        public ?int $referenceId = null,
        public ?string $reason = 'withdrawal',
        public ?string $description = null,
        public array $metadata = [],
        public ?int $expiresInSeconds = null,
    ) {}
}
