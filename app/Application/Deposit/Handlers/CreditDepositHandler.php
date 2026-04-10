<?php

namespace App\Application\Deposit\Handlers;

use App\Application\Deposit\Commands\CreditDepositCommand;
use App\Domain\Deposit\Repositories\DepositRepository;
use App\Domain\Deposit\ValueObjects\DepositId;
use App\Domain\Deposit\ValueObjects\DepositStatus;
use App\Domain\Ledger\Contracts\LedgerService;
use Illuminate\Support\Facades\DB;

final class CreditDepositHandler
{
    public function __construct(
        private readonly DepositRepository $deposits,
        private readonly LedgerService $ledgerService,
    ) {}

    public function handle(CreditDepositCommand $command): void
    {
        DB::transaction(function () use ($command): void {
            $deposit = $this->deposits->findById(new DepositId($command->depositId));

            if (! $deposit) {
                throw new \RuntimeException("Deposit {$command->depositId} not found.");
            }

            if ($deposit->status() === DepositStatus::Credited) {
                return;
            }

            if ($deposit->status() !== DepositStatus::Confirmed) {
                throw new \RuntimeException('Only confirmed deposits can be credited.');
            }

            $this->ledgerService->postDepositCredit(
                depositId: $deposit->id()->value(),
                userId: $deposit->userId(),
                currencyId: $deposit->currencyId(),
                amount: $deposit->amount(),
                operationId: $command->operationId,
                metadata: array_merge($deposit->metadata(), $command->metadata),
            );

            $deposit->markCredited($command->operationId);
            $this->deposits->save($deposit);
        });
    }
}
