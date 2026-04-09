<?php

namespace App\Domain\Deposit\ValueObjects;

enum DepositStatus: string
{
    case Detected = 'detected';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Credited = 'credited';
    case Failed = 'failed';
    case Reorged = 'reorged';
}
