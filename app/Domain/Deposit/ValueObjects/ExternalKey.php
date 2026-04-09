<?php

namespace App\Domain\Deposit\ValueObjects;

final class ExternalKey
{
    public function __construct(private string $value)
    {
        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('ExternalKey cannot be empty.');
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
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
