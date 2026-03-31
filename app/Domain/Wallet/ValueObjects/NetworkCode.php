<?php

namespace App\Domain\Wallet\ValueObjects;

final class NetworkCode
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        $allowed = ['ethereum', 'tron', 'bitcoin'];

        if (!in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException("Unsupported network");
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
