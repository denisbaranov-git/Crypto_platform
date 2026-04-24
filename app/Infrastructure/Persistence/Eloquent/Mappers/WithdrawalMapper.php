<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\Withdrawal\Entities\Withdrawal;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentWithdrawal;

final class WithdrawalMapper
{
    public function toDomain(EloquentWithdrawal $model): Withdrawal
    {
        return Withdrawal::hydrate($model->toArray());
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
            'network_fee_currency_network_id' => $withdrawal->networkFeeCurrencyNetworkId(),
            'total_debit_amount' => $withdrawal->totalDebitAmount(),
            'fee_rule_id' => $withdrawal->feeRuleId(),
            'fee_snapshot' => $withdrawal->feeSnapshot()->toArray(),
            'ledger_hold_id' => $withdrawal->ledgerHoldId(),
            'reserve_operation_id' => $withdrawal->reserveOperationId(),
            'consume_operation_id' => $withdrawal->consumeOperationId(),
            'release_operation_id' => $withdrawal->releaseOperationId(),
            'reversal_operation_id' => $withdrawal->reversalOperationId(),
            'system_wallet_id' => $withdrawal->systemWalletId(),
            'txid' => $withdrawal->txid()?->value(),
            'broadcast_attempts' => $withdrawal->broadcastAttempts(),
            'status' => $withdrawal->status()->value(),
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
            'confirmed_block_number' => $withdrawal->confirmedBlockNumber(),
            'confirmed_block_hash' => $withdrawal->confirmedBlockHash(),
            'confirmed_confirmations' => $withdrawal->confirmedConfirmations(),
            'reorged_at' => $withdrawal->reorgedAt(),
            'reversed_at' => $withdrawal->reversedAt(),
            'reorg_reason' => $withdrawal->reorgReason(),
            'reversal_reason' => $withdrawal->reversalReason(),
            'reorg_block_number' => $withdrawal->reorgBlockNumber(),
            'reversal_attempts' => $withdrawal->reversalAttempts(),
            'reversal_last_error' => $withdrawal->reversalLastError(),
            'reversal_failed_at' => $withdrawal->reversalFailedAt(),
            'network_fee_posted_at' => $withdrawal->networkFeePostedAt(),
            'network_fee_operation_id' => $withdrawal->networkFeeOperationId(),
            'idempotency_key' => $withdrawal->idempotencyKey(),
            'version' => $withdrawal->version(),
            'metadata' => $withdrawal->metadata(),
        ]);

        return $model;
    }
}
