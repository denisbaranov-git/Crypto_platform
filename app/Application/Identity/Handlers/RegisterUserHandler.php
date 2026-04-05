<?php

namespace App\Application\Identity\Handlers;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\EmailAlreadyExists;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Identity\ValueObjects\Email;
use App\Domain\Identity\ValueObjects\PasswordHash;
use App\Domain\Shared\EventPublisher;
use App\Infrastructure\Auth\Contracts\AuthUserProvider;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentUser;

readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepository $users,
        private AuthUserProvider $eloquentUserProvider,
        private EventPublisher $events
    ) {}

    public function handle(RegisterUserCommand $command): EloquentUser
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
        $eloquentUser = $this->eloquentUserProvider->findById($user->id()->value());

        $this->events->publish($user->pullDomainEvents());

        return $eloquentUser;
    }
}
