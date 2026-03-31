<?php

namespace App\Domain\Wallet\ValueObjects;

class WalletAddressValue
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $address): self
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Invalid address');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(WalletAddressValue $other): bool
    {
        return $this->value === $other->value;
    }
}
