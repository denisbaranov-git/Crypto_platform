<?php

declare(strict_types=1);

namespace App\Domain\Ledger\ValueObjects;

use DomainException;

final readonly class LedgerPostingLine
{
    private function __construct(
        public int $accountId,
        public LedgerDirection $direction,
        public string $amount,
        public array $metadata = [],
    ) {
        if (!is_numeric($this->amount) || bccomp($this->amount, '0', 18) <= 0) {
            throw new DomainException('Posting line amount must be a positive numeric string.');
        }
    }

    public static function debit(int $accountId, string $amount, array $metadata = []): self
    {
        return new self($accountId, LedgerDirection::Debit, $amount, $metadata);
    }

    public static function credit(int $accountId, string $amount, array $metadata = []): self
    {
        return new self($accountId, LedgerDirection::Credit, $amount, $metadata);
    }
}
