<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\Withdrawal\Entities\Withdrawal;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentWithdrawal;

final class WithdrawalMapper
{
    public function toDomain(EloquentWithdrawal $model): Withdrawal
    {
        return Withdrawal::hydrate([
            'id' => $model->id,
            'user_id' => $model->user_id,
            'network_id' => $model->network_id,
            'currency_network_id' => $model->currency_network_id,
            'destination_address' => $model->destination_address,
            'destination_tag' => $model->destination_tag,
            'amount' => (string) $model->amount,
            'fee_amount' => (string) $model->fee_amount,
            'network_fee_estimated_amount' => $model->network_fee_estimated_amount,
            'network_fee_actual_amount' => $model->network_fee_actual_amount,
            'total_debit_amount' => (string) $model->total_debit_amount,
            'fee_rule_id' => $model->fee_rule_id,
            'fee_snapshot' => $model->fee_snapshot,
            'ledger_hold_id' => $model->ledger_hold_id,
            'reserve_operation_id' => $model->reserve_operation_id,
            'consume_operation_id' => $model->consume_operation_id,
            'release_operation_id' => $model->release_operation_id,
            'reversal_operation_id' => $model->reversal_operation_id,
            'system_wallet_id' => $model->system_wallet_id,
            'txid' => $model->txid,
            'broadcast_attempts' => $model->broadcast_attempts,
            'status' => $model->status,
            'requested_at' => optional($model->requested_at)?->toDateTimeString(),
            'reserved_at' => optional($model->reserved_at)?->toDateTimeString(),
            'broadcasted_at' => optional($model->broadcasted_at)?->toDateTimeString(),
            'settled_at' => optional($model->settled_at)?->toDateTimeString(),
            'confirmed_at' => optional($model->confirmed_at)?->toDateTimeString(),
            'cancelled_at' => optional($model->cancelled_at)?->toDateTimeString(),
            'failed_at' => optional($model->failed_at)?->toDateTimeString(),
            'released_at' => optional($model->released_at)?->toDateTimeString(),
            'reorged_at' => optional($model->reorged_at)?->toDateTimeString(),
            'reversed_at' => optional($model->reversed_at)?->toDateTimeString(),
            'failure_reason' => $model->failure_reason,
            'cancellation_reason' => $model->cancellation_reason,
            'rejection_reason' => $model->rejection_reason,
            'reorg_reason' => $model->reorg_reason,
            'reversal_reason' => $model->reversal_reason,
            'last_error' => $model->last_error,
            'confirmed_block_number' => $model->confirmed_block_number,
            'confirmed_block_hash' => $model->confirmed_block_hash,
            'confirmed_confirmations' => $model->confirmed_confirmations,
            'reorg_block_number' => $model->reorg_block_number,
            'reversal_attempts' => $model->reversal_attempts,
            'reversal_last_error' => $model->reversal_last_error,
            'reversal_failed_at' => optional($model->reversal_failed_at)?->toDateTimeString(),
            'idempotency_key' => $model->idempotency_key,
            'version' => $model->version,
            'metadata' => $model->metadata ?? [],
        ]);
    }

    public function toModel(EloquentWithdrawal $model, Withdrawal $withdrawal): EloquentWithdrawal
    {
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
            'reversal_operation_id' => $withdrawal->reversalOperationId(),
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
            'reorged_at' => $withdrawal->reorgedAt(),
            'reversed_at' => $withdrawal->reversedAt(),
            'failure_reason' => $withdrawal->failureReason(),
            'cancellation_reason' => $withdrawal->cancellationReason(),
            'rejection_reason' => $withdrawal->rejectionReason(),
            'reorg_reason' => $withdrawal->reorgReason(),
            'reversal_reason' => $withdrawal->reversalReason(),
            'last_error' => $withdrawal->lastError(),
            'confirmed_block_number' => $withdrawal->confirmedBlockNumber(),
            'confirmed_block_hash' => $withdrawal->confirmedBlockHash(),
            'confirmed_confirmations' => $withdrawal->confirmedConfirmations(),
            'reorg_block_number' => $withdrawal->reorgBlockNumber(),
            'reversal_attempts' => $withdrawal->reversalAttempts(),
            'reversal_last_error' => $withdrawal->reversalLastError(),
            'reversal_failed_at' => $withdrawal->reversalFailedAt(),
            'idempotency_key' => $withdrawal->idempotencyKey(),
            'version' => $withdrawal->version(),
            'metadata' => $withdrawal->metadata(),
        ]);

        return $model;
    }
}
