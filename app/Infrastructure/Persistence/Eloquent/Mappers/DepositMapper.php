<?php

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\Deposit\Entities\Deposit;
use App\Domain\Deposit\ValueObjects\DepositStatus;
use App\Domain\Shared\ValueObjects\BlockNumber;
use App\Domain\Shared\ValueObjects\ExternalKey;
//use App\Domain\Shared\ValueObjects\TransactionHash;
use App\Domain\Shared\ValueObjects\TxId;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentDeposit;

final class DepositMapper
{
    public function toEntity(EloquentDeposit $model): Deposit
    {
        return Deposit::hydrate(
            id: (int) $model->id,
            userId: (int) $model->user_id,
            networkId: (int) $model->network_id,
            currencyNetworkId: (int) $model->currency_network_id,
            walletAddressId: (int) $model->wallet_address_id,
            externalKey: ExternalKey::fromString($model->external_key),
            txid:  TxId::fromString((string) $model->txid),
            amount: (string) $model->amount,
            toAddress: (string) $model->to_address,
            status: DepositStatus::from((string) $model->status),
            fromAddress: $model->from_address,
            blockHash: $model->block_hash,
            blockNumber: $model->block_number !== null ? BlockNumber::fromInt( $model->block_number) : null,
            confirmations: (int) $model->confirmations,
            detectedAt: $model->detected_at?->toImmutable(),
            confirmedAt: $model->confirmed_at?->toImmutable(),
            creditedAt: $model->credited_at?->toImmutable(),
            finalizedAt: $model->finalized_at?->toImmutable(),
            failedAt: $model->failed_at?->toImmutable(),
            failureReason: $model->failure_reason,
            metadata: $model->metadata ?? [],
            creditedOperationId: $model->credited_operation_id,
            reversalOperationId: $model->reversal_operation_id,
            reorgedAt: $model->reorged_at?->toImmutable(),
            reversedAt: $model->reversed_at?->toImmutable(),
            reorgReason: $model->reorg_reason,
            reversalReason: $model->reversal_reason,
            reorgBlockNumber: $model->reorg_block_number,
            reversalAttempts: $model->reversal_attempts ??  0,
            reversalLastError: $model->reversal_last_error,
            reversalFailedAt: $model->reversal_failed_at?->toImmutable(),
        );
    }

    public function toModel(Deposit $deposit, ?EloquentDeposit $model = null): EloquentDeposit
    {
        $model = $model ?? new EloquentDeposit();

        $model->user_id = $deposit->userId();
        $model->network_id = $deposit->networkId();
        $model->currency_network_id = $deposit->currencyNetworkId();
        $model->wallet_address_id = $deposit->walletAddressId();
        $model->external_key = $deposit->externalKey()->value();
        $model->txid = $deposit->txid()->value();
        $model->from_address = $deposit->fromAddress();
        $model->to_address = $deposit->toAddress();
        $model->amount = $deposit->amount();
//        $model->asset_type = $deposit->metadata()['asset_type'] ?? 'native';
//        $model->contract_address = $deposit->metadata()['contract_address'] ?? null;
        $model->block_hash = $deposit->blockHash();
        $model->block_number = $deposit->blockNumber()?->value();
        $model->confirmations = $deposit->confirmations();
        $model->status = $deposit->status()->value;
        $model->detected_at = $deposit->detectedAt();
        $model->confirmed_at = $deposit->confirmedAt();
        $model->credited_at = $deposit->creditedAt();
        $model->finalized_at = $deposit->finalizedAt();
        $model->failed_at = $deposit->failedAt();
        $model->failure_reason = $deposit->failureReason();
        $model->metadata = $deposit->metadata();

        $model->credited_operation_id = $deposit->creditedOperationId();
        $model->reversal_operation_id = $deposit->reversalOperationId();
        $model->reorged_at = $deposit->reorgedAt();
        $model->reversed_at = $deposit->reversedAt();
        $model->reorg_reason = $deposit->reorgReason();
        $model->reversal_reason = $deposit->reversalReason();
        $model->reorg_block_number = $deposit->reorgBlockNumber();
        $model->reversal_attempts = $deposit->reversalAttempts();
        $model->reversal_last_error = $deposit->reversalLastError();
        $model->reversal_failed_at = $deposit->reversalFailedAt();

        return $model;
    }
}
