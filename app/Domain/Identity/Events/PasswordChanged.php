<?php

namespace App\Domain\Identity\Events;

class PasswordChanged
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $name
    ){}
}
