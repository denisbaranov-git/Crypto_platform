<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Services;

use App\Domain\Withdrawal\Entities\Withdrawal;

interface WithdrawalRoutingService
{
    public function selectSystemWallet(Withdrawal $withdrawal): int;

    public function driverForWithdrawal(Withdrawal $withdrawal): string;
}
