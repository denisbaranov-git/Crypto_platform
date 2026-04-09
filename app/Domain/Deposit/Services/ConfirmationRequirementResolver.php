<?php

namespace App\Domain\Deposit\Services;

use App\Domain\Deposit\ValueObjects\ConfirmationRequirement;

interface ConfirmationRequirementResolver
{
    public function resolve(int $currencyNetworkId, string $amount): ConfirmationRequirement;
}
