<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Handlers;

use App\Application\Withdrawal\Commands\ReleaseWithdrawalFundsCommand;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Infrastructure\Ledger\Services\EloquentLedgerService;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReleaseWithdrawalFundsHandler
{
    public function __construct(
        private readonly WithdrawalRepository $withdrawals,
        private readonly EloquentLedgerService $ledger,
    ) {}

    public function handle(ReleaseWithdrawalFundsCommand $command): void
    {
        DB::transaction(function () use ($command): void {
            $withdrawal = $this->withdrawals->lockById($command->withdrawalId);

            if (! $withdrawal) {
                throw new DomainException('Withdrawal not found.');
            }

            if ($withdrawal->ledgerHoldId() === null) {
                return;
            }

            $this->ledger->releaseFunds(
                holdId: $command->holdId,
                operationId: $command->operationId,
                referenceType: 'withdrawal',
                referenceId: $command->withdrawalId,
                metadata: $command->metadata
            );

            $withdrawal->markReleased($command->operationId);
            $this->withdrawals->save($withdrawal);
        });
    }
}
