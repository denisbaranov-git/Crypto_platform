<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Entities;

use DomainException;

/**
 * LedgerOperation = шапка финансовой операции.
 *
 * Зачем нужна:
 * - идемпотентность;
 * - группировка проводок;
 * - жизненный цикл операции;
 * - связь с внешним reference (Deposit, Withdrawal, Transfer и т.д.).
 *
 * Без этой сущности очень легко словить:
 * - double credit;
 * - partial posting;
 * - отсутствие traceability.
 */
final class LedgerOperation
{
    public function __construct(
        private string $id,
        private string $idempotencyKey,
        private string $type,
        private string $status = 'pending',
        private ?string $referenceType = null,
        private ?int $referenceId = null,
        private ?string $description = null,
        private array $metadata = [],
        private ?string $postedAt = null,
        private ?string $failedAt = null,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function referenceType(): ?string
    {
        return $this->referenceType;
    }

    public function referenceId(): ?int
    {
        return $this->referenceId;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function postedAt(): ?string
    {
        return $this->postedAt;
    }

    public function failedAt(): ?string
    {
        return $this->failedAt;
    }

    public function markPosted(): void
    {
        if ($this->status === 'posted') {
            throw new DomainException('Operation is already posted.');
        }

        $this->status = 'posted';
        $this->postedAt = now()->toDateTimeString();
    }

    public function markFailed(): void
    {
        $this->status = 'failed';
        $this->failedAt = now()->toDateTimeString();
    }

    public function reverse(): void
    {
        if ($this->status !== 'posted') {
            throw new DomainException('Only posted operation can be reversed.');
        }

        $this->status = 'reversed';
    }
}
