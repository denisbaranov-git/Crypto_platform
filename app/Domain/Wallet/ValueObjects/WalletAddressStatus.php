<?php

namespace App\Domain\Wallet\ValueObjects;

enum WalletAddressStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ARCHIVED = 'archived';
}
