<?php

namespace App\Domain\Deposit\ValueObjects;

enum DepositStatus : string
{
    case DETECTED = 'detected';
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CREDITED = 'credited';
    case REORGED = 'reorged';
    case FAILED = 'failed';
}
