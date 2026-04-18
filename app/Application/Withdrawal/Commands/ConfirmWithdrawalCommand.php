<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Commands;

final readonly class ConfirmWithdrawalCommand
{
    public function __construct(
        public int $withdrawalId,
        //public int $confirmations, //old
        public array $metadata = [],
    ) {}
}
