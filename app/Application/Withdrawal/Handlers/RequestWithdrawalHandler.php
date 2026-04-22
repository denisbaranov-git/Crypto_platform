<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Handlers;

use App\Application\Withdrawal\Commands\RequestWithdrawalCommand;
use App\Domain\Ledger\Repositories\LedgerHoldRepository;
use App\Domain\Withdrawal\Entities\Withdrawal;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Domain\Withdrawal\Services\WithdrawalEligibilityPolicy;
use App\Domain\Withdrawal\Services\WithdrawalFeeCalculator;
use App\Domain\Withdrawal\ValueObjects\WithdrawalAddress;
use App\Domain\Shared\ValueObjects\Amount;
use App\Domain\Withdrawal\ValueObjects\WithdrawalTag;
use App\Infrastructure\Ledger\Services\EloquentLedgerService;
use App\Infrastructure\Withdrawal\Jobs\BroadcastWithdrawalJob;
use DomainException;
use Illuminate\Support\Facades\DB;


final class RequestWithdrawalHandler
{
    public function __construct(
        private readonly WithdrawalRepository $withdrawals,
        private readonly WithdrawalFeeCalculator $feeCalculator,
        private readonly WithdrawalEligibilityPolicy $eligibilityPolicy,
        private readonly EloquentLedgerService $ledger,
        private readonly LedgerHoldRepository $ledgerHolds,
    ) {}

    public function handle(RequestWithdrawalCommand $command): int
    {
        $withdrawalId = DB::transaction(function () use ($command): int {
            if ($existing = $this->withdrawals->byIdempotencyKey($command->idempotencyKey)) {
                return $existing->id()->value();
            }

            $amount = new Amount($command->amount);
            $destinationAddress = new WithdrawalAddress($command->destinationAddress);
            $destinationTag = new WithdrawalTag($command->destinationTag);
            /**
            Политика доступности
            WithdrawalEligibilityPolicy проверяет:
            -user активен
            -network pair активен
            -withdrawal разрешён
            -amount в диапазоне min/max
             */
            $this->eligibilityPolicy->assertCanWithdraw(
                userId: $command->userId,
                networkId: $command->networkId,
                currencyNetworkId: $command->currencyNetworkId,
                amount: $amount,
                context: $command->metadata,
            );
            /**
             * Rule selection
             *
             * take all fee_rules for currency_network_id
             * filter by min_amount/max_amount
             * sort by priority DESC
             * choose first
             * compute fee
             * save fee snapshot
             * set total_debit_amount = amount + fee_amount
             */
            $feeSnapshot = $this->feeCalculator->quote(
                currencyNetworkId: $command->currencyNetworkId,
                amount: $amount,
                context: $command->metadata,
            );

            $feeAmount = $this->feeCalculator->calculateFeeAmount($amount, $feeSnapshot);
            $totalDebitAmount = $this->feeCalculator->calculateTotalDebitAmount($amount, $feeAmount);

            $withdrawal = Withdrawal::request(
                userId: $command->userId,
                networkId: $command->networkId,
                currencyNetworkId: $command->currencyNetworkId,
                destinationAddress: $destinationAddress,
                destinationTag: $destinationTag,
                amount: $amount,
                feeAmount: $feeAmount,
                networkFeeEstimatedAmount: null,
                totalDebitAmount: $totalDebitAmount,
                feeRuleId: (int) ($feeSnapshot->toArray()['fee_rule_id']),
                feeSnapshot: $feeSnapshot,
                idempotencyKey: $command->idempotencyKey,
                metadata: $command->metadata,
            );

            $withdrawal = $this->withdrawals->save($withdrawal);

            $reserveOperationId = 'withdrawal:' . $withdrawal->id()->value() . ':reserve';
            /**
             * Резерв средств
             * LedgerService::reserveFunds():
             * создаёт ledger_operations
             * создаёт ledger_holds
             * увеличивает reserved_balance
             * пишет reserve_operation_id
             * Withdrawal получает:
             * ledger_hold_id
             * status = reserved
             */
            //denis  need refactor  to   //ReserveWithdrawalFundsHandler      !!!!!!!!!!!!
            $this->ledger->reserveFunds(
                userId: $command->userId,
                currencyNetworkId: $command->currencyNetworkId,
                amount: $totalDebitAmount,
                operationId: $reserveOperationId,
                referenceType: 'withdrawal',
                referenceId: $withdrawal->id()->value(),
                metadata: array_merge($command->metadata, [
                    'withdrawal_id' => $withdrawal->id()->value(),
                    'idempotency_key' => $command->idempotencyKey,
                ]),
                expiresInSeconds: 900
            );

            $hold = $this->ledgerHolds->findByLedgerOperationId($reserveOperationId);

            if (! $hold) {
                throw new DomainException('Ledger hold not found after reservation.');
            }

            $withdrawal->markReserved(
                holdId: $hold->id(),
                reserveOperationId: $reserveOperationId
            );

            $this->withdrawals->save($withdrawal);

            return $withdrawal->id()->value();
        });

        DB::afterCommit(function () use ($withdrawalId): void {
            dispatch(new BroadcastWithdrawalJob($withdrawalId));
        });

        return $withdrawalId;
    }
}
