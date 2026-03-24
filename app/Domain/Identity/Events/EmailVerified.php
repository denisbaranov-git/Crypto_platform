<?php

namespace App\Domain\Identity\Events;

class EmailVerified
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $name
    ){}
}
