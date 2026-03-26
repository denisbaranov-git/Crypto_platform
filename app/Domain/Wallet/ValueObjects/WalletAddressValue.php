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
        $address = trim($address);

        return new self($address);
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
