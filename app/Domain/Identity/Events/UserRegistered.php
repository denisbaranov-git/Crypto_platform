<?php

namespace App\Domain\Identity\Events;

class UserRegistered
{
    public function __construct(
        public readonly ?int $id, //denis
        public readonly string $email,
        public readonly string $name
    ){}
}
