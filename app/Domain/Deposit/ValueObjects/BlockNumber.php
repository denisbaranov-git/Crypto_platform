<?php

namespace App\Domain\Deposit\ValueObjects;

final class BlockNumber
{
    public function __construct(private int $value)
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('BlockNumber cannot be negative.');
        }

        $this->value = $value;
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
