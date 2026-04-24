<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Handlers;

use App\Application\Withdrawal\Commands\HandleWithdrawalReorgCommand;
use App\Application\Withdrawal\Commands\UpdateWithdrawalConfirmationsCommand;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Domain\Withdrawal\Services\WithdrawalConfirmationRequirementResolver;
use App\Infrastructure\Ledger\Services\EloquentLedgerService;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentCurrencyNetwork;
use DomainException;
use Illuminate\Support\Facades\DB;

final class UpdateWithdrawalConfirmationsHandler
{
    public function __construct(
        private readonly WithdrawalRepository $withdrawals,
        private readonly WithdrawalConfirmationRequirementResolver $requirements,
        private readonly EloquentLedgerService $ledger,
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

            if ($withdrawal->status() === 'confirmed') {
                return;
            }

            if (! in_array($withdrawal->status(), ['broadcasted', 'settled'], true)) {
                return;
            }

            if ($command->blockNumber !== null && $command->blockHash !== null) {
                $withdrawal->setConfirmedSnapshot(
                    blockNumber: $command->blockNumber,
                    blockHash: $command->blockHash,
                    confirmations: $command->confirmations
                );
            }

            $required = $this->requirements->resolve(
                $command->currencyNetworkId,
                $withdrawal->amount()->value()
            );

            if (! $command->finalized && $command->confirmations < $required) {
                $this->withdrawals->save($withdrawal);
                return;
            }

            $withdrawal->markConfirmed(
                confirmations: $command->confirmations,
                blockNumber: $command->blockNumber,
                blockHash: $command->blockHash
            );

            $this->withdrawals->save($withdrawal);

            // Network fee booking only after confirmed/finalized.
            if ($command->actualFeeAmount !== null && bccomp($command->actualFeeAmount, '0', 18) > 0) {
                $nativeCurrencyNetworkId = $this->resolveNativeCurrencyNetworkId($command->networkId);

                $this->ledger->recordWithdrawalNetworkFeeExpense(
                    withdrawalId: $withdrawal->id()->value(),
                    currencyNetworkId: $nativeCurrencyNetworkId,
                    amount: $command->actualFeeAmount,
                    operationId: 'withdrawal:' . $withdrawal->id()->value() . ':network_fee:' . $withdrawal->txid()->value(),
                    metadata: array_merge($command->metadata, [
                        'txid' => $withdrawal->txid()->value(),
                        'fee_currency_code' => $command->feeCurrencyCode,
                    ])
                );
            }
        });
    }

    private function resolveNativeCurrencyNetworkId(int $networkId): int
    {
        $native = EloquentCurrencyNetwork::query()
            ->where('network_id', $networkId)
            ->whereNull('contract_address')
            ->firstOrFail();

        return (int) $native->id;
    }
}
