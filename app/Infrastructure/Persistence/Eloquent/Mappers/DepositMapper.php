<?php

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\Deposit\Entities\Deposit;
use App\Domain\Deposit\ValueObjects\BlockNumber;
use App\Domain\Deposit\ValueObjects\DepositStatus;
use App\Domain\Deposit\ValueObjects\ExternalKey;
use App\Domain\Deposit\ValueObjects\TransactionHash;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentDeposit;

final class DepositMapper
{
    public function toEntity(EloquentDeposit $model): Deposit
    {
//        Нужно сохранить и читать новые поля:
//
//        credited_operation_id
//        reversal_operation_id
//        reorged_at
//        reversed_at
//        reorg_reason
//        reversal_reason
//        reorg_block_number
//        reversal_attempts
//        reversal_last_error
//        reversal_failed_at
        error //denis

 //public static function hydrate(
//        int $id,
//        int $userId,
//        int $networkId,
//        int $currencyNetworkId,
//        int $walletAddressId,
//        ExternalKey $externalKey,
//        TransactionHash $txid,
//        string $amount,
//        string $toAddress,
//        DepositStatus $status,
//        ?string $fromAddress = null,
//        ?string $blockHash = null,
//        ?BlockNumber $blockNumber = null,
//        int $confirmations = 0,
//        ?\DateTimeImmutable $detectedAt = null,
//        ?\DateTimeImmutable $confirmedAt = null,
//        ?\DateTimeImmutable $creditedAt = null,
//        ?\DateTimeImmutable $finalizedAt = null,
//        ?\DateTimeImmutable $failedAt = null,
//        ?string $failureReason = null,
//        array $metadata = [],
//        // Ledger lifecycle
//        ?string $creditedOperationId = null,
//        ?string $reversalOperationId = null,
//        ?\DateTimeImmutable $reorgedAt = null,
//        ?\DateTimeImmutable $reversedAt = null,
//        ?string $reorgReason = null,
//        ?string $reversalReason = null,
//        ?int $reorgBlockNumber = null,
//        int $reversalAttempts = 0,
//        ?string $reversalLastError = null,
//        ?\DateTimeImmutable $reversalFailedAt = null,

        return Deposit::hydrate(
            id: (int) $model->id,
            userId: (int) $model->user_id,
            networkId: (int) $model->network_id,
            currencyNetworkId: (int) $model->currency_network_id,
            walletAddressId: (int) $model->wallet_address_id,
            externalKey: new ExternalKey((string) $model->external_key),
            txid: new TransactionHash((string) $model->txid),
            amount: (string) $model->amount,
            toAddress: (string) $model->to_address,
            status: DepositStatus::from((string) $model->status),
            fromAddress: $model->from_address,
            blockHash: $model->block_hash,
            blockNumber: $model->block_number !== null ? new BlockNumber((int) $model->block_number) : null,
            confirmations: (int) $model->confirmations,
            detectedAt: $model->detected_at?->toImmutable(),
            confirmedAt: $model->confirmed_at?->toImmutable(),
            creditedAt: $model->credited_at?->toImmutable(),
            finalizedAt: $model->finalized_at?->toImmutable(),
            failedAt: $model->failed_at?->toImmutable(),
            failureReason: $model->failure_reason,
            metadata: $model->metadata ?? [],
        );
    }

    public function toModel(Deposit $deposit, ?EloquentDeposit $model = null): EloquentDeposit
    {
        $model = $model ?? new EloquentDeposit();

        $model->user_id = $deposit->userId();
        //$model->currency_id = $deposit->currencyId();
        $model->network_id = $deposit->networkId();
        $model->currency_network_id = $deposit->currencyNetworkId();
        $model->wallet_address_id = $deposit->walletAddressId();
        $model->external_key = $deposit->externalKey()->value();
        $model->txid = $deposit->txid()->value();
        $model->from_address = $deposit->fromAddress();
        $model->to_address = $deposit->toAddress();
        $model->amount = $deposit->amount();
        $model->asset_type = $deposit->metadata()['asset_type'] ?? 'native';
        $model->contract_address = $deposit->metadata()['contract_address'] ?? null;
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

        return $model;
    }
}
