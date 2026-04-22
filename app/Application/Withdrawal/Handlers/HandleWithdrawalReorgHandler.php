<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Handlers;

use App\Application\Withdrawal\Commands\HandleWithdrawalReorgCommand;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Infrastructure\Ledger\Services\EloquentLedgerService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * CHANGED:
 * - this is the compensation path after a real chain reorg;
 * - it never rebroadcasts the same withdrawal;
 * - it reverses ledger only if consume already happened.
 */
final class HandleWithdrawalReorgHandler
{
    public function __construct(
        private readonly WithdrawalRepository $withdrawals,
        private readonly EloquentLedgerService $ledger,
    ) {}

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

            if (! in_array($withdrawal->status(), ['reorged', 'settled', 'confirmed'], true)) {
                return;
            }

            if ($withdrawal->consumeOperationId() === null) {
                // Broadcast existed but consume never happened.
                // No ledger reversal required.
                $withdrawal->markFailed('reorg_without_settlement', $command->reason);
                $this->withdrawals->save($withdrawal);
                return;
            }

            try {
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
            } catch (\Throwable $e) {
                $withdrawal->incrementReversalAttempts($e->getMessage());
                $withdrawal->recordLastError($e->getMessage());
                $this->withdrawals->save($withdrawal);

                throw $e;
            }
        });
    }
}
