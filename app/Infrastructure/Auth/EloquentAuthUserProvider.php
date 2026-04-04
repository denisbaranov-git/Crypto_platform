<?php

namespace App\Infrastructure\Auth;

use App\Infrastructure\Auth\Contracts\AuthUserProvider;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentUser;

class EloquentAuthUserProvider implements AuthUserProvider
{

    public function findByEmail(string $email): ?EloquentUser
    {
        return EloquentUser::where('email', $email)->first();
    }
    public function findById(int $id): ?EloquentUser
    {
        return EloquentUser::find($id);
    }
}
