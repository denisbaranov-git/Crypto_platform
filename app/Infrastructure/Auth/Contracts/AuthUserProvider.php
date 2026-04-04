<?php

namespace App\Infrastructure\Auth\Contracts;

use App\Infrastructure\Persistence\Eloquent\Models\EloquentUser;

interface AuthUserProvider
{
    public function findByEmail(string $email): ?EloquentUser;
}
