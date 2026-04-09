<?php

namespace App\Application\Deposit\Commands;

final readonly class ConfirmDepositCommand
{
    public function __construct(
        public int $depositId
    ) {}
}
