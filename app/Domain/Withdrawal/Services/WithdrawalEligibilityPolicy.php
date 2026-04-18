<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Services;

use App\Domain\Shared\ValueObjects\Amount;

interface WithdrawalEligibilityPolicy
{
    public function assertCanWithdraw(
        int    $userId,
        int    $networkId,
        int    $currencyNetworkId,
        Amount $amount,
        array  $context = []
    ): void;
}
