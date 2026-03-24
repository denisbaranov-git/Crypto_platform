<?php

namespace App\Domain\Identity\ValueObjects;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case BLOCKED = 'blocked';
    case PENDING = 'pending';
}
