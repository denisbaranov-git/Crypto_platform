<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\EmailAlreadyExists;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Identity\ValueObjects\Email;
use App\Domain\Identity\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentUser;
use Illuminate\Database\QueryException;

class EloquentUserRepository implements UserRepository
{
    public function __construct(private UserMapper $mapper){}
    public function save(User $user): void
    {
        $model = $user->id() ? EloquentUser::findOrFail($user->id()->value()) : null;
        $model = $this->mapper->toModel($user, $model);

        try {
            $model->save();
        } catch (QueryException $e) {
//            if ($this->isUniqueConstraint($e)) {
//                throw new EmailAlreadyExists();
//            }
            throw $e;
        }

        if (!$user->id()) {
            $user->assignId(UserId::fromInt($model->id));
        }
    }

    public function findByEmail(Email $email): ?User
    {
        $model = EloquentUser::where('email', $email->value())->first();

        if (!$model) {
            return null;
        }

        return $this->mapper->ToDomain($model);
    }

    public function existsByEmail(Email $email): bool
    {
        return EloquentUser::where('email', $email->value())->exists();
    }
}
