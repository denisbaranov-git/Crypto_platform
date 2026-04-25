<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Entities;

use App\Domain\Shared\RecordsDomainEvents;
use App\Domain\Shared\ValueObjects\Amount;
use App\Domain\Shared\ValueObjects\TxId;
use App\Domain\Withdrawal\ValueObjects\WithdrawalAddress;
//use App\Domain\Withdrawal\ValueObjects\WithdrawalAmount;
use App\Domain\Withdrawal\ValueObjects\WithdrawalFeeSnapshot;
use App\Domain\Withdrawal\ValueObjects\WithdrawalId;
use App\Domain\Withdrawal\ValueObjects\WithdrawalStatus;
use App\Domain\Withdrawal\ValueObjects\WithdrawalTag;
//use App\Domain\Withdrawal\ValueObjects\WithdrawalTxId;
use DomainException;

final class Withdrawal
{
    use RecordsDomainEvents;

    public function __construct(
        private ?WithdrawalId $id,
        private int $userId,
        private int $networkId,
        private int $currencyNetworkId,
        private WithdrawalAddress $destinationAddress,
        private ?WithdrawalTag $destinationTag,
        private Amount $amount,
        private string $feeAmount,
        private ?string $networkFeeEstimatedAmount,
        private ?string $networkFeeActualAmount,
        private ?int $networkFeeCurrencyNetworkId,
        private string $totalDebitAmount,
        private ?int $feeRuleId,
        private WithdrawalFeeSnapshot $feeSnapshot,
        private ?int $ledgerHoldId,
        private ?string $reserveOperationId,
        private ?string $consumeOperationId,
        private ?string $releaseOperationId,
        private ?string $reversalOperationId,
        private ?int $systemWalletId,
        private ?TxId $txid,
        private int $broadcastAttempts,
        private WithdrawalStatus $status,
        private ?string $requestedAt,
        private ?string $reservedAt,
        private ?string $broadcastedAt,
        private ?string $settledAt,
        private ?string $confirmedAt,
        private ?string $cancelledAt,
        private ?string $failedAt,
        private ?string $releasedAt,
        private ?string $failureReason,
        private ?string $cancellationReason,
        private ?string $rejectionReason,
        private ?string $lastError,
//        private ?int $confirmedBlockNumber,
//        private ?string $confirmedBlockHash,
//        private ?int $confirmedConfirmations,
        private ?int $blockNumber,
        private ?string $blockHash,
        private ?int $confirmations,
        private ?string $reorgedAt,
        private ?string $reversedAt,
        private ?string $reorgReason,
        private ?string $reversalReason,
        private ?int $reorgBlockNumber,
        private ?int $reversalAttempts,
        private ?string $reversalLastError,
        private ?string $reversalFailedAt,
        private ?string $networkFeePostedAt,
        private ?string $networkFeeOperationId,
        private string $idempotencyKey,
        private int $version,
        private array $metadata = [],
    ) {
        $this->assertInvariant();
    }

    public static function request(
        int $userId,
        int $networkId,
        int $currencyNetworkId,
        WithdrawalAddress $destinationAddress,
        ?WithdrawalTag $destinationTag,
        Amount $amount,
        string $feeAmount,
        ?string $networkFeeEstimatedAmount,
        string $totalDebitAmount,
        ?int $feeRuleId,
        WithdrawalFeeSnapshot $feeSnapshot,
        string $idempotencyKey,
        array $metadata = [],
    ): self {
        return new self(
            id: null,
            userId: $userId,
            networkId: $networkId,
            currencyNetworkId: $currencyNetworkId,
            destinationAddress: $destinationAddress,
            destinationTag: $destinationTag,
            amount: $amount,
            feeAmount: $feeAmount,
            networkFeeEstimatedAmount: $networkFeeEstimatedAmount,
            networkFeeActualAmount: null,
            networkFeeCurrencyNetworkId: null,
            totalDebitAmount: $totalDebitAmount,
            feeRuleId: $feeRuleId,
            feeSnapshot: $feeSnapshot,
            ledgerHoldId: null,
            reserveOperationId: null,
            consumeOperationId: null,
            releaseOperationId: null,
            reversalOperationId: null,
            systemWalletId: null,
            txid: null,
            broadcastAttempts: 0,
            status: new WithdrawalStatus('requested'),
            requestedAt: now()->toDateTimeString(),
            reservedAt: null,
            broadcastedAt: null,
            settledAt: null,
            confirmedAt: null,
            cancelledAt: null,
            failedAt: null,
            releasedAt: null,
            failureReason: null,
            cancellationReason: null,
            rejectionReason: null,
            lastError: null,
            blockNumber: null,
            blockHash: null,
            confirmations: null,
            reorgedAt: null,
            reversedAt: null,
            reorgReason: null,
            reversalReason: null,
            reorgBlockNumber: null,
            reversalAttempts: 0,
            reversalLastError: null,
            reversalFailedAt: null,
            networkFeePostedAt: null,
            networkFeeOperationId: null,
            idempotencyKey: $idempotencyKey,
            version: 0,
            metadata: $metadata,
        );
    }

    public static function hydrate(array $row): self
    {
        return new self(
            id: isset($row['id']) ? WithdrawalId::fromInt((int) $row['id']) : null,
            userId: (int) $row['user_id'],
            networkId: (int) $row['network_id'],
            currencyNetworkId: (int) $row['currency_network_id'],
            destinationAddress: new WithdrawalAddress((string) $row['destination_address']),
            destinationTag: ! empty($row['destination_tag']) ? new WithdrawalTag((string) $row['destination_tag']) : null,
            amount: new Amount((string) $row['amount']),
            feeAmount: (string) $row['fee_amount'],
            networkFeeEstimatedAmount: $row['network_fee_estimated_amount'] !== null ? (string) $row['network_fee_estimated_amount'] : null,
            networkFeeActualAmount: $row['network_fee_actual_amount'] !== null ? (string) $row['network_fee_actual_amount'] : null,
            networkFeeCurrencyNetworkId: $row['network_fee_currency_network_id'] !== null ? (int) $row['network_fee_currency_network_id'] : null,
            totalDebitAmount: (string) $row['total_debit_amount'],
            feeRuleId: $row['fee_rule_id'] !== null ? (int) $row['fee_rule_id'] : null,
            feeSnapshot: WithdrawalFeeSnapshot::fromArray((array) $row['fee_snapshot']),
            ledgerHoldId: $row['ledger_hold_id'] !== null ? (int) $row['ledger_hold_id'] : null,
            reserveOperationId: $row['reserve_operation_id'] ?? null,
            consumeOperationId: $row['consume_operation_id'] ?? null,
            releaseOperationId: $row['release_operation_id'] ?? null,
            reversalOperationId: $row['reversal_operation_id'] ?? null,
            systemWalletId: $row['system_wallet_id'] !== null ? (int) $row['system_wallet_id'] : null,
            txid: ! empty($row['txid']) ? new TxId((string) $row['txid']) : null,
            broadcastAttempts: (int) ($row['broadcast_attempts'] ?? 0),
            status: new WithdrawalStatus((string) $row['status']),
            requestedAt: $row['requested_at'] ?? null,
            reservedAt: $row['reserved_at'] ?? null,
            broadcastedAt: $row['broadcasted_at'] ?? null,
            settledAt: $row['settled_at'] ?? null,
            confirmedAt: $row['confirmed_at'] ?? null,
            cancelledAt: $row['cancelled_at'] ?? null,
            failedAt: $row['failed_at'] ?? null,
            releasedAt: $row['released_at'] ?? null,
            failureReason: $row['failure_reason'] ?? null,
            cancellationReason: $row['cancellation_reason'] ?? null,
            rejectionReason: $row['rejection_reason'] ?? null,
            lastError: $row['last_error'] ?? null,
            blockNumber: $row['block_number'] !== null ? (int) $row['block_number'] : null,
            blockHash: $row['block_hash'] ?? null,
            confirmations: $row['confirmations'] !== null ? (int) $row['confirmations'] : null,
            reorgedAt: $row['reorged_at'] ?? null,
            reversedAt: $row['reversed_at'] ?? null,
            reorgReason: $row['reorg_reason'] ?? null,
            reversalReason: $row['reversal_reason'] ?? null,
            reorgBlockNumber: $row['reorg_block_number'] !== null ? (int) $row['reorg_block_number'] : null,
            reversalAttempts: $row['reversal_attempts'] !== null ? (int) $row['reversal_attempts'] : 0,
            reversalLastError: $row['reversal_last_error'] ?? null,
            reversalFailedAt: $row['reversal_failed_at'] ?? null,
            networkFeePostedAt: $row['network_fee_posted_at'] ?? null,
            networkFeeOperationId: $row['network_fee_operation_id'] ?? null,
            idempotencyKey: (string) $row['idempotency_key'],
            version: (int) ($row['version'] ?? 0),
            metadata: (array) ($row['metadata'] ?? []),
        );
    }

    public function id(): ?WithdrawalId { return $this->id; }
    public function userId(): int { return $this->userId; }
    public function networkId(): int { return $this->networkId; }
    public function currencyNetworkId(): int { return $this->currencyNetworkId; }
    public function destinationAddress(): WithdrawalAddress { return $this->destinationAddress; }
    public function destinationTag(): ?WithdrawalTag { return $this->destinationTag; }
    public function amount(): Amount { return $this->amount; }
    public function feeAmount(): string { return $this->feeAmount; }
    public function networkFeeEstimatedAmount(): ?string { return $this->networkFeeEstimatedAmount; }
    public function networkFeeActualAmount(): ?string { return $this->networkFeeActualAmount; }
    public function networkFeeCurrencyNetworkId(): ?int { return $this->networkFeeCurrencyNetworkId; }
    public function totalDebitAmount(): string { return $this->totalDebitAmount; }
    public function feeRuleId(): ?int { return $this->feeRuleId; }
    public function feeSnapshot(): WithdrawalFeeSnapshot { return $this->feeSnapshot; }
    public function ledgerHoldId(): ?int { return $this->ledgerHoldId; }
    public function reserveOperationId(): ?string { return $this->reserveOperationId; }
    public function consumeOperationId(): ?string { return $this->consumeOperationId; }
    public function releaseOperationId(): ?string { return $this->releaseOperationId; }
    public function reversalOperationId(): ?string { return $this->reversalOperationId; }
    public function systemWalletId(): ?int { return $this->systemWalletId; }
    public function txid(): ?TxId { return $this->txid; }
    public function broadcastAttempts(): int { return $this->broadcastAttempts; }
    public function status(): WithdrawalStatus { return $this->status; }
    public function requestedAt(): ?string { return $this->requestedAt; }
    public function reservedAt(): ?string { return $this->reservedAt; }
    public function broadcastedAt(): ?string { return $this->broadcastedAt; }
    public function settledAt(): ?string { return $this->settledAt; }
    public function confirmedAt(): ?string { return $this->confirmedAt; }
    public function cancelledAt(): ?string { return $this->cancelledAt; }
    public function failedAt(): ?string { return $this->failedAt; }
    public function releasedAt(): ?string { return $this->releasedAt; }
    public function failureReason(): ?string { return $this->failureReason; }
    public function cancellationReason(): ?string { return $this->cancellationReason; }
    public function rejectionReason(): ?string { return $this->rejectionReason; }
    public function lastError(): ?string { return $this->lastError; }
//    public function confirmedBlockNumber(): ?int { return $this->blockNumber; }
//    public function confirmedBlockHash(): ?string { return $this->blockHash; }
//    public function confirmedConfirmations(): ?int { return $this->confirmations; }
    public function blockNumber(): ?int { return $this->blockNumber; }
    public function blockHash(): ?string { return $this->blockHash; }
    public function confirmations(): ?int { return $this->confirmations; }
    public function reorgedAt(): ?string { return $this->reorgedAt; }
    public function reversedAt(): ?string { return $this->reversedAt; }
    public function reorgReason(): ?string { return $this->reorgReason; }
    public function reversalReason(): ?string { return $this->reversalReason; }
    public function reorgBlockNumber(): ?int { return $this->reorgBlockNumber; }
    public function reversalAttempts(): ?int { return $this->reversalAttempts; }
    public function reversalLastError(): ?string { return $this->reversalLastError; }
    public function reversalFailedAt(): ?string { return $this->reversalFailedAt; }
    public function networkFeePostedAt(): ?string { return $this->networkFeePostedAt; }
    public function networkFeeOperationId(): ?string { return $this->networkFeeOperationId; }
    public function idempotencyKey(): string { return $this->idempotencyKey; }
    public function version(): int { return $this->version; }
    public function metadata(): array { return $this->metadata; }

    public function markReserved(int $holdId, string $reserveOperationId): void
    {
        if ($this->status->value() !== 'requested') {
            throw new DomainException('Only requested withdrawal can be reserved.');
        }

        $this->ledgerHoldId = $holdId;
        $this->reserveOperationId = $reserveOperationId;
        $this->status = new WithdrawalStatus('reserved');
        $this->reservedAt = now()->toDateTimeString();
        $this->version++;
    }

    public function markBroadcastPending(): void
    {
        if (! in_array($this->status->value(), ['requested', 'reserved', 'broadcast_pending'], true)) {
            throw new DomainException('Only requested/reserved withdrawal can become broadcast_pending.');
        }

        $this->status = new WithdrawalStatus('broadcast_pending');
        $this->version++;
    }

    public function markBroadcasted(TxId $txid, int $systemWalletId): void
    {
        if (! in_array($this->status->value(), ['reserved', 'broadcast_pending'], true)) {
            throw new DomainException('Only reserved/broadcast_pending withdrawal can be broadcasted.');
        }

        $this->txid = $txid;
        $this->systemWalletId = $systemWalletId;
        $this->broadcastAttempts++;
        $this->broadcastedAt = now()->toDateTimeString();
        $this->status = new WithdrawalStatus('broadcasted');
        $this->version++;
    }

    public function markSettled(string $consumeOperationId): void
    {
        if (! in_array($this->status->value(), ['broadcasted', 'settled'], true)) {
            throw new DomainException('Only broadcasted withdrawal can be settled.');
        }

        $this->consumeOperationId = $consumeOperationId;
        $this->settledAt = now()->toDateTimeString();
        $this->status = new WithdrawalStatus('settled');
        $this->version++;
    }

    public function markConfirmed(int $confirmations, ?int $blockNumber = null, ?string $blockHash = null): void
    {
        if (! in_array($this->status->value(), ['broadcasted', 'settled', 'confirmed'], true)) {
            throw new DomainException('Only broadcasted/settled withdrawal can be confirmed.');
        }

        $this->confirmations = $confirmations;
        $this->blockNumber = $blockNumber;
        $this->blockHash = $blockHash;
        $this->confirmedAt = now()->toDateTimeString();
        $this->status = new WithdrawalStatus('confirmed');
        $this->version++;
    }

    public function markReleased(string $reason): void
    {
        $this->releasedAt = now()->toDateTimeString();
        $this->status = new WithdrawalStatus('released');
        $this->failureReason = $reason;
        $this->version++;
    }

    public function markCancelled(string $reason): void
    {
        $this->cancelledAt = now()->toDateTimeString();
        $this->cancellationReason = $reason;
        $this->status = new WithdrawalStatus('cancelled');
        $this->version++;
    }

    public function markFailed(string $reason): void
    {
        $this->failedAt = now()->toDateTimeString();
        $this->failureReason = $reason;
        $this->status = new WithdrawalStatus('failed');
        $this->version++;
    }

    public function markReorged(string $reason, ?int $reorgBlockNumber = null): void
    {
        $this->reorgedAt = now()->toDateTimeString();
        $this->reorgReason = $reason;
        $this->reorgBlockNumber = $reorgBlockNumber;
        $this->status = new WithdrawalStatus('reorged');
        $this->version++;
    }

    public function markReversed(string $reason, string $reversalOperationId): void
    {
        $this->reversedAt = now()->toDateTimeString();
        $this->reversalReason = $reason;
        $this->reversalOperationId = $reversalOperationId;
        $this->status = new WithdrawalStatus('reversed');
        $this->version++;
    }

    public function markNetworkFeeBooked(int $currencyNetworkId, string $amount, string $operationId): void
    {
        $this->networkFeeCurrencyNetworkId = $currencyNetworkId;
        $this->networkFeeActualAmount = $amount;
        $this->networkFeeOperationId = $operationId;
        $this->networkFeePostedAt = now()->toDateTimeString();
        $this->version++;
    }
    public function setConfirmSnapshot(
        int $blockNumber,
        string $blockHash,
        int $confirmations
    ): void {
        if ($blockNumber <= 0) {
            throw new DomainException('Block number must be greater than zero.');
        }

        if ($blockHash === '') {
            throw new DomainException('Block hash cannot be empty.');
        }

        if ($confirmations < 0) {
            throw new DomainException('Confirmations cannot be negative.');
        }

        $this->blockNumber = $blockNumber;
        $this->blockHash = $blockHash;
        $this->confirmations = $confirmations;
    }
    public function recordLastError(string $message): void
    {
        $this->lastError = $message;
        $this->version++;
    }

    private function assertInvariant(): void
    {
        if ($this->amount->isZero()) {
            throw new DomainException('Withdrawal amount must be greater than zero.');
        }
    }
}
