<?php

declare(strict_types=1);

namespace App\Application\Deposit\Handlers;

use App\Application\Deposit\Commands\ReverseDepositCreditCommand;
use App\Domain\Ledger\Contracts\LedgerService;

final class ReverseDepositCreditHandler
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {}

    public function handle(ReverseDepositCreditCommand $command): void
    {
        $this->ledgerService->reverseDepositCredit(
            depositId: $command->depositId,
            metadata: $command->metadata
        );
    }
}
