<?php

namespace App\Application\Identity\Handlers;

use App\Application\Identity\Commands\LoginUserCommand;
//use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\InvalidCredentials;
//use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Identity\ValueObjects\Email;
use App\Domain\Identity\ValueObjects\PasswordHash;
use App\Domain\Shared\EventPublisher;
use App\Infrastructure\Auth\Contracts\AuthUserProvider;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentUser;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Hash;

readonly class LoginUserHandler
{
    public function __construct(
        //private UserRepository $users,
        private AuthUserProvider $users,
        //private EventPublisher $events
    ) {}

    //public function handle(LoginUserCommand $command): User
    public function handle(LoginUserCommand $command): EloquentUser
    {
        $email = Email::fromString($command->email);

        $user = $this->users->findByEmail($email->value());

        if (!$user) {
            throw new InvalidCredentials("user is $user");
        }
        $password_hash = PasswordHash::fromHash($user->password);

        //if (!Hash::check($command->password, $user->password)) {
        if (!$password_hash->verify($command->password)) {
            throw new InvalidCredentials("password invalid");
        }

        if ($user->status !== 'active') {
            throw new InvalidCredentials("user status is $user->status");
        }

//        if (!$user->verifyPassword($command->password)) {
//            throw new InvalidCredentials();
//        }
//
//        if (!$user->canAuthenticate()) {
//            throw new InvalidCredentials();
//        }
//
//        $this->events->publish($user->pullDomainEvents());

        return $user;
    }
}
