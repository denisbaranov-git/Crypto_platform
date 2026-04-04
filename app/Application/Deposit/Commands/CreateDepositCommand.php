<?php

namespace App\Application\Deposit\Commands;

class CreateDepositCommand
{
    public function __construct(
        public int $depositId,
    ) {}
}
