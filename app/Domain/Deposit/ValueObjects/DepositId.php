<?php

namespace App\Domain\Deposit\ValueObjects;

final class DepositId
{
    public function __construct(private string $value)
    {
        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('DepositId cannot be empty.');
        }

        $this->value = $value;
    }

    public static function fromString(int|string $value): self
    {
        return new self((string) $value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
