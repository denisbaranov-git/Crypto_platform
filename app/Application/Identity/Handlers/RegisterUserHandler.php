<?php

namespace App\Application\Identity\Handlers;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\EmailAlreadyExists;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Identity\ValueObjects\Email;
use App\Domain\Identity\ValueObjects\PasswordHash;

readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepository $users
    ) {}

    public function handle(RegisterUserCommand $command): User
    {
        $email = Email::fromString($command->email);

        if ($this->users->existsByEmail($email)) {
            throw new EmailAlreadyExists();
        }

        $user = User::register(
            $command->name,
            $email,
            PasswordHash::fromPlain($command->password)
        );

        $this->users->save($user);

        return $user;
    }
}
