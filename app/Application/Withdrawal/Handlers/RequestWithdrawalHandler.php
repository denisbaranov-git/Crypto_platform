<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Handlers;

use App\Application\Withdrawal\Commands\RequestWithdrawalCommand;
use App\Domain\Ledger\Repositories\LedgerHoldRepository;
use App\Domain\Shared\ValueObjects\Amount;
use App\Domain\Withdrawal\Entities\Withdrawal;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Domain\Withdrawal\Services\WithdrawalEligibilityPolicy;
use App\Domain\Withdrawal\Services\WithdrawalFeeCalculator;
use App\Domain\Withdrawal\ValueObjects\WithdrawalAddress;
use App\Domain\Withdrawal\ValueObjects\WithdrawalTag;
use App\Infrastructure\Ledger\Services\EloquentLedgerService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * CHANGED:
 * - request + reserve happen in one DB transaction;
 * - no outbox is needed for the internal next step;
 * - controller dispatches BroadcastWithdrawalJob after this returns.
 */
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
        return DB::transaction(function () use ($command): int {
            if ($existing = $this->withdrawals->byIdempotencyKey($command->idempotencyKey)) { //denis ключ из input!!!!!!!!!!!!!!!!!!!????????????
                return $existing->id()->value();
            }

            $amount = new Amount($command->amount);
            $destinationAddress = new WithdrawalAddress($command->destinationAddress);
            $destinationTag = new WithdrawalTag($command->destinationTag);

            $this->eligibilityPolicy->assertCanWithdraw(
                userId: $command->userId,
                networkId: $command->networkId,
                currencyNetworkId: $command->currencyNetworkId,
                amount: $amount,
                context: $command->metadata,
            );

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

            $withdrawal = $this->withdrawals->save($withdrawal);

            //dispatch(new BroadcastWithdrawalJob($withdrawalId));
            return $withdrawal->id()->value();
        });
    }
}
