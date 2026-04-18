<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Commands;

final readonly class ReconcileStuckWithdrawalsCommand
{
//Урегулировать застрявшие запросы на вывод средств
    public function __construct(
        public int $networkId,
    ) {}
}
