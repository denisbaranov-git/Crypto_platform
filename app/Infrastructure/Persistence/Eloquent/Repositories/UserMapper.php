<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Identity\ValueObjects\Email;
use App\Domain\Identity\ValueObjects\PasswordHash;
use App\Domain\Identity\ValueObjects\UserStatus;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentUser;

class UserMapper
{
    public function toDomain(EloquentUser $model): User
    {
        return User::hydrate(
            id: UserId::fromInt($model->id),
            name: $model->name,
            email: Email::fromString($model->email),
            password: PasswordHash::fromHash($model->password),
            status: UserStatus::from($model->status),
            emailVerifiedAt: $model->email_verified_at
                ? \DateTimeImmutable::createFromInterface($model->email_verified_at)
                : null
        );
    }

    public function toModel(User $user, ?EloquentUser $model = null): EloquentUser
    {
        //$model = $user->id()? EloquentUser::findOrFail($user->id()->value()) : new EloquentUser();
        $model = $model ?? new EloquentUser();

        $model->name = $user->name();
        $model->email = $user->email()->value();
        $model->password = $user->password()->value();
        $model->status = $user->status()->value;
        $model->email_verified_at = $user->emailVerifiedAt();

        return $model;
    }
}
