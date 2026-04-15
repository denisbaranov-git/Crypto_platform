<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Contracts;

use App\Domain\Ledger\ValueObjects\LedgerPostingLine;

interface LedgerPostingService
{
    /**
     * @param LedgerPostingLine[] $lines
     *
     * Важно:
     * - этот метод НЕ открывает DB transaction;
     * - transaction boundary живёт в use-case service;
     * - posting service только атомарно пишет проводки внутри уже открытой транзакции.
     */
    public function post(
        string $idempotencyKey,
        string $type,
        ?string $referenceType,
        ?int $referenceId,
        array $lines,
        array $metadata = []
    ): string;
}
