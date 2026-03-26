<?php

namespace App\Domain\Wallet\ValueObjects;

class DerivationPath
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $derivationPath): self
    {
        $derivationPath = trim($derivationPath);

        return new self($derivationPath);
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
