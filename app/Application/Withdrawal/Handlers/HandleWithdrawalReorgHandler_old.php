<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Handlers;

use App\Application\Withdrawal\Commands\HandleWithdrawalReorgCommand;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Infrastructure\Ledger\Services\EloquentLedgerService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * - this is the compensation path after a real chain reorg;
 * - it never rebroadcasts the same withdrawal;
 * - it reverses ledger only if consume already happened.
 */
final class HandleWithdrawalReorgHandler_old
{
    public function __construct(
        private readonly WithdrawalRepository $withdrawals,
        private readonly EloquentLedgerService $ledger,
    ) {}
    /**
     * withdrawal ранее становится reorged
     * если consume_operation_id есть:
     * LedgerService::reverseWithdrawalConsumption()
     * reversal posting: debit clearing, credit user
     * withdrawal становится reversed
     * old withdrawal не rebroadcast’ится
     * для нового вывода создаётся новый withdrawal request
     */
    public function handle(HandleWithdrawalReorgCommand $command): void
    {
        DB::transaction(function () use ($command): void {
            $withdrawal = $this->withdrawals->lockById($command->withdrawalId);

            if (! $withdrawal) {
                throw new DomainException('Withdrawal not found.');
            }

            if ($withdrawal->status() === 'reversed') {
                return;
            }

            if (! in_array($withdrawal->status(), ['broadcasted', 'settled', 'confirmed', 'reorged'], true)) {
                return;
            }

            if ($withdrawal->consumeOperationId() === null) {
                // No ledger consumption happened.
                // Release hold and close the withdrawal as reorged/released.
                $this->ledger->releaseFunds(
                    holdId: $withdrawal->ledgerHoldId(),
                    operationId: 'withdrawal:' . $withdrawal->id()->value() . ':release',
                    referenceType: 'withdrawal',
                    referenceId: $withdrawal->id()->value(),
                    metadata: [
                        'reason' => $command->reason,
                        'mode' => 'reorg_before_consume',
                    ]
                );

                $withdrawal->markReorged($command->reason, $withdrawal->reorgBlockNumber() ?? null);
                $withdrawal->released_at = now();
                $withdrawal->failure_reason = 'reorg_before_consume';
                $this->withdrawals->save($withdrawal);

                return;
            }

            $this->ledger->reverseWithdrawalConsumption(
                withdrawalId: $withdrawal->id()->value(),
                metadata: array_merge($command->metadata, [
                    'reason' => $command->reason,
                    'txid' => $withdrawal->txid()?->value(),
                ])
            );

            $withdrawal->markReversed(
                reason: $command->reason,
                reversalOperationId: 'withdrawal:' . $withdrawal->id()->value() . ':reversal'
            );
            $this->withdrawals->save($withdrawal);
        });
    }
}
