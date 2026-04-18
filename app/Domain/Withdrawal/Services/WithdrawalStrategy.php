<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Domain\Shared\ValueObjects\TxId;
use App\Domain\Withdrawal\Entities\Withdrawal;

interface WithdrawalStrategy
{
    public function driver(): string;

    public function estimateNetworkFee(Withdrawal $withdrawal): string;

    public function broadcast(Withdrawal $withdrawal, array $context = []): TxId;

    public function requiredConfirmations(Withdrawal $withdrawal): int;

    public function supports(Withdrawal $withdrawal): bool;
}
