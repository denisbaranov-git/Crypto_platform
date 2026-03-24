<?php

namespace App\Application\Identity\Handlers;

use App\Application\Identity\Commands\LoginUserCommand;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\InvalidCredentials;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Identity\ValueObjects\Email;
use App\Domain\Identity\ValueObjects\PasswordHash;
use Illuminate\Contracts\Events\Dispatcher;

readonly class LoginUserHandler
{
    public function __construct(
        private UserRepository $users,
    ) {}

    public function handle(LoginUserCommand $command): User
    {
        $email = Email::fromString($command->email);

        $user = $this->users->findByEmail($email);

        if (!$user) {
            throw new InvalidCredentials();
        }

        if (!$user->verifyPassword($command->password)) {
            throw new InvalidCredentials();
        }

        if (!$user->canAuthenticate()) {
            throw new InvalidCredentials();
        }

        foreach ($user->pullEvents() as $event) {
            //$this->eventDispatcher->dispatch($event);
            event($event);
        }

        return $user;
    }
}
