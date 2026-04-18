<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\ValueObjects;

use DomainException;

final class WithdrawalId
{
    public function __construct(private readonly int $value)
    {
        if ($value <= 0) {
            throw new DomainException('WithdrawalId must be greater than zero.');
        }
    }

    public function value(): int
    {
        return $this->value;
    }
}
