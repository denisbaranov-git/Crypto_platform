<?php

namespace App\Domain\Identity\ValueObjects;

final class PasswordHash
{
    private string $hash;

    private function __construct(string $hash)
    {
        $this->hash = $hash;
    }

    public static function fromPlain(string $password): self
    {
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password too short');
        }

        return new self(password_hash($password, PASSWORD_BCRYPT));
    }

    public static function fromHash(string $hash): self
    {
        return new self($hash);
    }

    public function verify(string $plain): bool
    {
        return password_verify($plain, $this->hash);
    }

    public function value(): string
    {
        return $this->hash;
    }
}
