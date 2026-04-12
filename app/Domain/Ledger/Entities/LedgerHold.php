<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Entities;

use DomainException;

/**
 * LedgerHold = доменное удержание средств.
 *
 * Почему отдельная сущность:
 * - reserved_balance даёт быстрый aggregate state;
 * - hold даёт причину, lifecycle и управляемость.
 */
final class LedgerHold
{
    public function __construct(
        private ?int $id,
        private string $ledgerOperationId,
        private int $accountId,
        private int $currencyNetworkId,
        private string $amount,
        private string $status = 'active', // active | released | consumed | expired
        private ?string $reason = null,
        private ?string $expiresAt = null,
        private ?string $releasedAt = null,
        private ?string $consumedAt = null,
        private array $metadata = [],
    ) {}

    public function id(): ?int { return $this->id; }
    public function ledgerOperationId(): string { return $this->ledgerOperationId; }
    public function accountId(): int { return $this->accountId; }
    public function currencyNetworkId(): int { return $this->currencyNetworkId; }
    public function amount(): string { return $this->amount; }
    public function status(): string { return $this->status; }
    public function reason(): ?string { return $this->reason; }
    public function expiresAt(): ?string { return $this->expiresAt; }
    public function metadata(): array { return $this->metadata; }

    public function release(): void
    {
        if ($this->status !== 'active') {
            throw new DomainException('Only active hold can be released.');
        }

        $this->status = 'released';
        $this->releasedAt = now()->toDateTimeString();
    }

    public function consume(): void
    {
        if ($this->status !== 'active') {
            throw new DomainException('Only active hold can be consumed.');
        }

        $this->status = 'consumed';
        $this->consumedAt = now()->toDateTimeString();
    }

    public function expire(): void
    {
        if ($this->status !== 'active') {
            throw new DomainException('Only active hold can expire.');
        }

        $this->status = 'expired';
    }
}
