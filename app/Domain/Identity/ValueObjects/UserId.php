<?php

namespace App\Domain\Identity\ValueObjects;

final readonly class UserId
{
    public function __construct(private int $id)
    {}
    public static function fromInt(int $id): self
    {
        if ($id < 0) throw new \InvalidArgumentException('User ID cannot be less than zero');
        return new self($id);
    }
    public function value(): int
    {
        return $this->id;
    }
}
