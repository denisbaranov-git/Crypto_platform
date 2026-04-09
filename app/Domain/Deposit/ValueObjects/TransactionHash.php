<?php

namespace App\Domain\Deposit\ValueObjects;

final class TransactionHash
{
    public function __construct(private string $value)
    {
        $value = strtolower(trim($value));

        if ($value === '') {
            throw new \InvalidArgumentException('TransactionHash cannot be empty.');
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

    public function __toString(): string
    {
        return $this->value;
    }
}
