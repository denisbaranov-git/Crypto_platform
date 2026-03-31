<?php

namespace App\Domain\Wallet\ValueObjects;

class DerivationPath
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        if (!str_starts_with($value, 'm/')) {
            throw new \InvalidArgumentException('Invalid derivation path');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(DerivationPath $other): bool
    {
        return $this->value === $other->value;
    }
}
