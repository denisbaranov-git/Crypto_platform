<?php

namespace App\Application\Identity\Commands;

class RegisterUserCommand
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password
    ) {}
}
