<?php

declare(strict_types=1);

namespace App\Application\Ledger\Commands;

/**
 * Команда для начисления средств на счёт.
 *
 * Для чего нужна:
 * - это вход в application layer;
 * - handler принимает команду и оркестрирует use case;
 * - command удобно отправлять из Deposit/Fiat/Outbox.
 */
final readonly class CreditAccountCommand
{
    public function __construct(
        public string $idempotencyKey,
        public string $ownerType,
        public int $ownerId,
        public int $currencyNetworkId,
        public string $amount,
        public string $referenceType,
        public ?int $referenceId = null,
        public ?string $description = null,
        public array $metadata = [],
    ) {}
}
