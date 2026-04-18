<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Services;

interface WithdrawalConfirmationRequirementResolver
{
    public function resolve(int $currencyNetworkId, string $amount): int;
}
