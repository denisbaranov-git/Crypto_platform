<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Services;

use App\Domain\Shared\ValueObjects\Amount;
use App\Domain\Withdrawal\ValueObjects\WithdrawalFeeSnapshot;

interface WithdrawalFeeCalculator
{
    public function quote(
        int    $currencyNetworkId,
        Amount $amount,
        array  $context = []
    ): WithdrawalFeeSnapshot;

    public function calculateFeeAmount(
        Amount                $amount,
        WithdrawalFeeSnapshot $snapshot
    ): string;

    public function calculateTotalDebitAmount(
        Amount $amount,
        string $feeAmount
    ): string;
}
