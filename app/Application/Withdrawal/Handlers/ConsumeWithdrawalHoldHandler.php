<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Handlers;

use App\Application\Withdrawal\Commands\ConsumeWithdrawalHoldCommand;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Infrastructure\Ledger\Services\EloquentLedgerService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * CHANGED:
 * - recovery-only handler;
 * - safe if hold is already consumed;
 * - safe if withdrawal is already settled.
 */
final class ConsumeWithdrawalHoldHandler
{
    public function __construct(
        private readonly WithdrawalRepository $withdrawals,
        private readonly EloquentLedgerService $ledger,
    ) {}

    public function handle(ConsumeWithdrawalHoldCommand $command): void
    {
        DB::transaction(function () use ($command): void {
            $withdrawal = $this->withdrawals->lockById($command->withdrawalId);

            if (! $withdrawal) {
                throw new DomainException('Withdrawal not found.');
            }

            if (in_array($withdrawal->status(), ['settled', 'confirmed'], true)) {
                return;
            }

            if ($withdrawal->ledgerHoldId() === null) {
                throw new DomainException('Withdrawal hold not found.');
            }

            $consumeOperationId = 'withdrawal:' . $withdrawal->id()->value() . ':consume';

            $this->ledger->consumeHold(
                holdId: $withdrawal->ledgerHoldId(),
                operationId: $consumeOperationId,
                referenceType: 'withdrawal',
                referenceId: $withdrawal->id()->value(),
                metadata: $command->metadata
            );

            $withdrawal->markSettled($consumeOperationId);
            $this->withdrawals->save($withdrawal);
        });
    }
}
