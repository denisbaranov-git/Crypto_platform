<?php

declare(strict_types=1);

namespace App\Application\Ledger\Commands;

/**
 * Команда окончательного списания ранее зарезервированных средств.
 *
 * Используется, когда withdrawal уже ушёл в сеть и должен
 * стать окончательным списанием из balance.
 */
final readonly class ConsumeHoldCommand
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
