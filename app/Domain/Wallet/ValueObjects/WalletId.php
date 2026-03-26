<?php

namespace App\Domain\Wallet\ValueObjects;

class WalletId
{
    private function __construct(private int $id)
    {}
    public static function fromInt(int $id): self
    {
        if ($id < 0) throw new \InvalidArgumentException('Wallet ID cannot be less than zero');
        return new self($id);
    }
    public function value(): int
    {
        return $this->id;
    }
}
