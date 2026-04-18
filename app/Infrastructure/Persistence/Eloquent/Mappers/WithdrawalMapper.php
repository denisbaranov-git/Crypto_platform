<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\Shared\ValueObjects\Amount;
use App\Domain\Shared\ValueObjects\TxId;
use App\Domain\Withdrawal\Entities\Withdrawal;
use App\Domain\Withdrawal\ValueObjects\WithdrawalAddress;
use App\Domain\Withdrawal\ValueObjects\WithdrawalFeeSnapshot;
use App\Domain\Withdrawal\ValueObjects\WithdrawalId;
use App\Domain\Withdrawal\ValueObjects\WithdrawalStatus;
use App\Domain\Withdrawal\ValueObjects\WithdrawalTag;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentWithdrawal;

final class WithdrawalMapper
{
    public function toDomain(EloquentWithdrawal $model): Withdrawal
    {
        return new Withdrawal( //denis hydrate // refactor like DepositMapper
            id: new WithdrawalId((int) $model->id),
            userId: (int) $model->user_id,
            networkId: (int) $model->network_id,
            currencyNetworkId: (int) $model->currency_network_id,
            destinationAddress: new WithdrawalAddress((string) $model->destination_address),
            destinationTag: $model->destination_tag ? new WithdrawalTag((string) $model->destination_tag) : null,
            amount: new Amount((string) $model->amount),
            feeAmount: (string) $model->fee_amount,
            networkFeeEstimatedAmount: $model->network_fee_estimated_amount,
            networkFeeActualAmount: $model->network_fee_actual_amount,
            totalDebitAmount: (string) $model->total_debit_amount,
            feeRuleId: $model->fee_rule_id ? (int) $model->fee_rule_id : null,
            feeSnapshot: $model->fee_snapshot ? new WithdrawalFeeSnapshot(
                feeRuleId: (string) ($model->fee_snapshot['fee_rule_id'] ?? ''),
                feeType: (string) ($model->fee_snapshot['fee_type'] ?? 'fixed'),
                fee: (string) ($model->fee_snapshot['fee'] ?? '0'),
                minAmount: $model->fee_snapshot['min_amount'] ?? null,
                maxAmount: $model->fee_snapshot['max_amount'] ?? null,
                priority: isset($model->fee_snapshot['priority']) ? (int) $model->fee_snapshot['priority'] : null,
                metadata: (array) ($model->fee_snapshot['metadata'] ?? []),
            ) : null,
            ledgerHoldId: $model->ledger_hold_id ? (int) $model->ledger_hold_id : null,
            reserveOperationId: $model->reserve_operation_id,
            consumeOperationId: $model->consume_operation_id,
            releaseOperationId: $model->release_operation_id,
            systemWalletId: $model->system_wallet_id ? (int) $model->system_wallet_id : null,
            txid: $model->txid ? new TxId((string) $model->txid) : null,
            broadcastAttempts: (int) $model->broadcast_attempts,
            status: new WithdrawalStatus((string) $model->status),
            requestedAt: $model->requested_at?->toDateTimeString(),
            reservedAt: $model->reserved_at?->toDateTimeString(),
            broadcastedAt: $model->broadcasted_at?->toDateTimeString(),
            settledAt: $model->settled_at?->toDateTimeString(),
            confirmedAt: $model->confirmed_at?->toDateTimeString(),
            cancelledAt: $model->cancelled_at?->toDateTimeString(),
            failedAt: $model->failed_at?->toDateTimeString(),
            releasedAt: $model->released_at?->toDateTimeString(),
            failureReason: $model->failure_reason,
            cancellationReason: $model->cancellation_reason,
            rejectionReason: $model->rejection_reason,
            lastError: $model->last_error,
            idempotencyKey: (string) $model->idempotency_key,
            version: (int) $model->version,
            metadata: $model->metadata ?? [],
        );
    }

    public function fillModel(EloquentWithdrawal $model, Withdrawal $withdrawal): EloquentWithdrawal
    {
        //$model = $model ?? new EloquentWithdrawal(); // refactor like DepositMapper

        $model->fill([
            'user_id' => $withdrawal->userId(),
            'network_id' => $withdrawal->networkId(),
            'currency_network_id' => $withdrawal->currencyNetworkId(),
            'destination_address' => $withdrawal->destinationAddress()->value(),
            'destination_tag' => $withdrawal->destinationTag()?->value(),
            'amount' => $withdrawal->amount()->value(),
            'fee_amount' => $withdrawal->feeAmount(),
            'network_fee_estimated_amount' => $withdrawal->networkFeeEstimatedAmount(),
            'network_fee_actual_amount' => $withdrawal->networkFeeActualAmount(),
            'total_debit_amount' => $withdrawal->totalDebitAmount(),
            'fee_rule_id' => $withdrawal->feeRuleId(),
            'fee_snapshot' => $withdrawal->feeSnapshot()?->toArray(),
            'ledger_hold_id' => $withdrawal->ledgerHoldId(),
            'reserve_operation_id' => $withdrawal->reserveOperationId(),
            'consume_operation_id' => $withdrawal->consumeOperationId(),
            'release_operation_id' => $withdrawal->releaseOperationId(),
            'system_wallet_id' => $withdrawal->systemWalletId(),
            'txid' => $withdrawal->txid()?->value(),
            'broadcast_attempts' => $withdrawal->broadcastAttempts(),
            'status' => $withdrawal->status(),
            'requested_at' => $withdrawal->requestedAt(),
            'reserved_at' => $withdrawal->reservedAt(),
            'broadcasted_at' => $withdrawal->broadcastedAt(),
            'settled_at' => $withdrawal->settledAt(),
            'confirmed_at' => $withdrawal->confirmedAt(),
            'cancelled_at' => $withdrawal->cancelledAt(),
            'failed_at' => $withdrawal->failedAt(),
            'released_at' => $withdrawal->releasedAt(),
            'failure_reason' => $withdrawal->failureReason(),
            'cancellation_reason' => $withdrawal->cancellationReason(),
            'rejection_reason' => $withdrawal->rejectionReason(),
            'last_error' => $withdrawal->lastError(),
            'idempotency_key' => $withdrawal->idempotencyKey(),
            'version' => $withdrawal->version(),
            'metadata' => $withdrawal->metadata(),
        ]);

        return $model;
    }
}
