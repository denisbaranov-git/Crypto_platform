<?php

declare(strict_types=1);

namespace App\Application\Ledger\Commands;

/**
 * Команда освобождения hold.
 *
 * Используется, когда:
 * - withdrawal отменён;
 * - hold истёк;
 * - manual release;
 * - risk/compliance unfreeze.
 */
final readonly class ReleaseFundsCommand
{
    public function __construct(
        public string $idempotencyKey,
        public int $holdId,
        public string $referenceType,
        public ?int $referenceId = null,
        public ?string $description = null,
        public array $metadata = [],
    ) {}
}
