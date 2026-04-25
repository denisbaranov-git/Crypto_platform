<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Handlers;

use App\Application\Withdrawal\Commands\HandleWithdrawalReorgCommand;
use App\Application\Withdrawal\Commands\UpdateWithdrawalConfirmationsCommand;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Domain\Withdrawal\Services\WithdrawalConfirmationRequirementResolver;
use DomainException;
use Illuminate\Support\Facades\DB;

final class UpdateWithdrawalConfirmationsHandler_old
{
    public function __construct(
        private readonly WithdrawalRepository $withdrawals,
        private readonly WithdrawalConfirmationRequirementResolver $requirements,
        private readonly HandleWithdrawalReorgHandler $handleReorg,
    ) {}

    public function handle(UpdateWithdrawalConfirmationsCommand $command): void
    {
        DB::transaction(function () use ($command): void {
            $withdrawal = $this->withdrawals->lockById($command->withdrawalId);

            if (! $withdrawal) {
                throw new DomainException('Withdrawal not found.');
            }

            if ($withdrawal->txid() === null || $withdrawal->txid()->value() !== $command->txid) {
                return;
            }

            // Reorg detection if we already had a confirmed snapshot and current block hash differs.
            if (
                $withdrawal->blockNumber() !== null &&
                $withdrawal->blockHash() !== null &&
                $command->blockNumber !== null &&
                $command->blockHash !== null &&
                $withdrawal->blockNumber() === $command->blockNumber &&
                $withdrawal->blockHash() !== $command->blockHash
            ) {
                /**
                 * withdrawal становится reorged
                 * запускается HandleWithdrawalReorgHandler
                 * если consume_operation_id есть:  <- BroadcastWithdrawalHandler:: $consumeOperationId = 'withdrawal:' . $withdrawal->id()->value() . ':consume';
                 * LedgerService::reverseWithdrawalConsumption()
                 * reversal posting: debit clearing, credit user
                 * withdrawal становится reversed
                 * old withdrawal не rebroadcast’ится
                 * для нового вывода создаётся новый withdrawal request
                 */
                $withdrawal->markReorged(
                    reason: 'canonical_block_hash_mismatch',
                    //reorgBlockNumber: $withdrawal->confirmedBlockNumber()
                    reorgBlockNumber: $withdrawal->blockNumber()
                );
                $this->withdrawals->save($withdrawal);

                $this->handleReorg->handle(new HandleWithdrawalReorgCommand( // HandleWithdrawalReorgHandler
                    withdrawalId: $withdrawal->id()->value(),
                    reason: 'canonical_block_hash_mismatch',
                    metadata: $command->metadata
                ));

                return;
            }

            if ($withdrawal->status() === 'confirmed') {
                return;
            }

            if (! in_array($withdrawal->status(), ['broadcasted', 'settled'], true)) {
                return;
            }

            $required = $this->requirements->resolve(
                $command->currencyNetworkId,
                $withdrawal->amount()->value()
            );

            if (! $command->finalized && $command->confirmations < $required) {
                return;
            }

            $withdrawal->markConfirmed(
                confirmations: $command->confirmations,
                blockNumber: $command->blockNumber,
                blockHash: $command->blockHash
            );

            $this->withdrawals->save($withdrawal);
            //$ledger->recordWithdrawalNetworkFeeExpense($withdrawalId,$amount,$operationId,$metadata = []);

        });
    }
}
