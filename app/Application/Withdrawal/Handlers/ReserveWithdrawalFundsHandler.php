<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Handlers;

use App\Application\Withdrawal\Commands\ReserveWithdrawalFundsCommand;
use App\Domain\Ledger\Repositories\LedgerHoldRepository;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Infrastructure\Ledger\Services\EloquentLedgerService;
use DomainException;
use Illuminate\Support\Facades\DB;

//Здесь важна одна практическая деталь: чтобы markReserved() получил holdId,
//репозиторий после reserveFunds() должен перечитать withdrawal и связанный hold.
final class ReserveWithdrawalFundsHandler
{
    public function __construct(
        private readonly WithdrawalRepository $withdrawals,
        private readonly EloquentLedgerService $ledger,
        private readonly LedgerHoldRepository $ledgerHolds,
    ) {}

    public function handle(ReserveWithdrawalFundsCommand $command): void
    {
        DB::transaction(function () use ($command): void {
            $withdrawal = $this->withdrawals->lockById($command->withdrawalId);

            if (! $withdrawal) {
                throw new DomainException('Withdrawal not found.');
            }

            if ($withdrawal->ledgerHoldId() !== null) {
                return;
            }

            $this->ledger->reserveFunds(
                userId: $command->userId,
                currencyNetworkId: $command->currencyNetworkId,
                amount: $command->amount,
                operationId: $command->operationId,
                referenceType: 'withdrawal',
                referenceId: $command->withdrawalId,
                metadata: $command->metadata,
                expiresInSeconds: 900
            );
//            // после reserveFunds() перечитаем withdrawal и связанный hold.
//            $fresh = $this->withdrawals->lockById($command->withdrawalId);
//            $fresh->markReserved(
//                holdId: $fresh->ledgerHoldId() ?? 0,
//                reserveOperationId: $command->operationId
//            );
//            $this->withdrawals->save($fresh);

            $hold = $this->ledgerHolds->findByLedgerOperationId($command->operationId);

            if (! $hold) {
                throw new DomainException('Ledger hold not found after reservation.');
            }

            $withdrawal->markReserved(
                holdId: $hold->id(),
                reserveOperationId: $command->operationId
            );

            $withdrawal = $this->withdrawals->save($withdrawal);
        });
    }
}
