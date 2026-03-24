<?php

namespace App\Application\Identity\Commands;

class LoginUserCommand
{
    public function __construct(
        public string $email,
        public string $password
    ){}

}
