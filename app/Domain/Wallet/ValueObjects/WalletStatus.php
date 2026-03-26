<?php

namespace App\Domain\Wallet\ValueObjects;

enum WalletStatus : string
{
    case ACTIVE = 'active';
    case LOCKED = 'locked';
    case ARCHIVED = 'archived';
}
