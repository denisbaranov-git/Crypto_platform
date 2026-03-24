<?php

namespace App\Domain\Identity\Repositories;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\ValueObjects\Email;

interface UserRepository
{
    public function save(User $user): void;

    public function findByEmail(Email $email): ?User;

    public function existsByEmail(Email $email): bool;
}
