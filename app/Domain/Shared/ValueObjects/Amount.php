<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use DomainException;

final class Amount
{
    private const SCALE = 18;

    public function __construct(private readonly string $value)
    {
        if (! preg_match('/^\d+(\.\d{1,18})?$/', $value)) {
            throw new DomainException('Amount must be a decimal string with up to 18 decimals.');
        }

        if (bccomp($value, '0', self::SCALE) <= 0) {
            throw new DomainException('Amount must be greater than zero.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
    public function isZero(): bool
    {
        return bccomp($this->value, '0', 18) === 0;
    }
}
