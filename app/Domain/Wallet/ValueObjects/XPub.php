<?php

namespace App\Domain\Wallet\ValueObjects;

final class XPub
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Invalid xpub');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
