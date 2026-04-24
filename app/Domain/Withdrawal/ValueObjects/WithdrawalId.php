<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\ValueObjects;

use DomainException;

final readonly class WithdrawalId
{
    public function __construct(private int $value)
    {
        if ($value <= 0) {
            throw new DomainException('WithdrawalId must be greater than zero.');
        }
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }
}
