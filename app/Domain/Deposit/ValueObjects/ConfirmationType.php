<?php

namespace App\Domain\Deposit\ValueObjects;

enum ConfirmationType: string
{
    case Blocks = 'blocks';
    case Finality = 'finality';
}
