<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Handlers;

use App\Application\Withdrawal\Commands\CancelWithdrawalCommand;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Infrastructure\Ledger\Services\EloquentLedgerService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Cancel
 *
 * If withdrawal is still reserved or broadcast_pending:
 *
 * call releaseFunds()
 * set withdrawal released
 * set withdrawal cancelled
 */
final class CancelWithdrawalHandler
{
    public function __construct(
        private readonly WithdrawalRepository $withdrawals,
        private readonly EloquentLedgerService $ledger,
    ) {}

    public function handle(CancelWithdrawalCommand $command): void
    {
        DB::transaction(function () use ($command): void {
            $withdrawal = $this->withdrawals->lockById($command->withdrawalId);

            if (! $withdrawal) {
                throw new DomainException('Withdrawal not found.');
            }

            $withdrawal->markCancelled($command->reason);

            if ($withdrawal->ledgerHoldId() !== null) {
                $releaseOperationId = 'withdrawal:' . $withdrawal->id()->value() . ':release';
                /**
                 * Важно: после reserveFunds() и commit нужно стартовать broadcast job.
                 * У тебя это можно делать в контроллере сразу после handler returns, либо через DB::afterCommit().
                */
                $this->ledger->releaseFunds(
                    holdId: $withdrawal->ledgerHoldId(),
                    operationId: $releaseOperationId,
                    referenceType: 'withdrawal',
                    referenceId: $withdrawal->id()->value(),
                    metadata: array_merge($command->metadata, [
                        'reason' => $command->reason,
                    ])
                );

                $withdrawal->markReleased($releaseOperationId);
            }

            $this->withdrawals->save($withdrawal);
        });
    }
}
