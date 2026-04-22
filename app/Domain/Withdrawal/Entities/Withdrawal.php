<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Entities;

use App\Domain\Shared\ValueObjects\Amount;
use App\Domain\Shared\ValueObjects\TxId;
use App\Domain\Withdrawal\Events\WithdrawalBroadcasted;
use App\Domain\Withdrawal\Events\WithdrawalCancelled;
use App\Domain\Withdrawal\Events\WithdrawalConfirmed;
use App\Domain\Withdrawal\Events\WithdrawalFailed;
use App\Domain\Withdrawal\Events\WithdrawalReleased;
use App\Domain\Withdrawal\Events\WithdrawalReorged;
use App\Domain\Withdrawal\Events\WithdrawalReversed;
use App\Domain\Withdrawal\Events\WithdrawalRequested;
use App\Domain\Withdrawal\Events\WithdrawalReserved;
use App\Domain\Withdrawal\Events\WithdrawalSettled;
use App\Domain\Withdrawal\ValueObjects\WithdrawalAddress;
use App\Domain\Withdrawal\ValueObjects\WithdrawalFeeSnapshot;
use App\Domain\Withdrawal\ValueObjects\WithdrawalId;
use App\Domain\Withdrawal\ValueObjects\WithdrawalStatus;
use App\Domain\Withdrawal\ValueObjects\WithdrawalTag;
use DomainException;

/**
 * CHANGED:
 * - withdrawal is a process aggregate, not a ledger fact table;
 * - hydrate() is used by the mapper;
 * - events are recorded locally;
 * - reorg/reversal lifecycle is explicit.
 */
final class Withdrawal
{
    /** @var array<int, object> */
    private array $recordedEvents = [];

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
        private string $totalDebitAmount,
        private ?int $feeRuleId = null,
        private ?WithdrawalFeeSnapshot $feeSnapshot = null,
        private ?int $ledgerHoldId = null,
        private ?string $reserveOperationId = null,
        private ?string $consumeOperationId = null,
        private ?string $releaseOperationId = null,
        private ?string $reversalOperationId = null,
        private ?int $systemWalletId = null,
        private ?TxId $txid = null,
        private int $broadcastAttempts = 0,
        private string $status = WithdrawalStatus::REQUESTED,
        private ?string $requestedAt = null,
        private ?string $reservedAt = null,
        private ?string $broadcastedAt = null,
        private ?string $settledAt = null,
        private ?string $confirmedAt = null,
        private ?string $cancelledAt = null,
        private ?string $failedAt = null,
        private ?string $releasedAt = null,
        private ?string $reorgedAt = null,
        private ?string $reversedAt = null,
        private ?string $failureReason = null,
        private ?string $cancellationReason = null,
        private ?string $rejectionReason = null,
        private ?string $reorgReason = null,
        private ?string $reversalReason = null,
        private ?string $lastError = null,
        private ?int $confirmedBlockNumber = null,
        private ?string $confirmedBlockHash = null,
        private ?int $confirmedConfirmations = null,
        private ?int $reorgBlockNumber = null,
        private int $reversalAttempts = 0,
        private ?string $reversalLastError = null,
        private ?string $reversalFailedAt = null,
        private string $idempotencyKey = '',
        private int $version = 0,
        private array $metadata = [],
    ) {
        $this->status = (new WithdrawalStatus($status))->value();
        $this->assertInvariant();
    }

    public static function hydrate(array $data): self
    {
        return new self(
            id: isset($data['id']) ? new WithdrawalId((int) $data['id']) : null,
            userId: (int) ($data['user_id'] ?? 0),
            networkId: (int) ($data['network_id'] ?? 0),
            currencyNetworkId: (int) ($data['currency_network_id'] ?? 0),
            destinationAddress: new WithdrawalAddress((string) ($data['destination_address'] ?? '')),
            destinationTag: array_key_exists('destination_tag', $data) && $data['destination_tag'] !== null
                ? new WithdrawalTag((string) $data['destination_tag'])
                : null,
            amount: new Amount((string) ($data['amount'] ?? '0')),
            feeAmount: (string) ($data['fee_amount'] ?? '0'),
            networkFeeEstimatedAmount: $data['network_fee_estimated_amount'] ?? null,
            networkFeeActualAmount: $data['network_fee_actual_amount'] ?? null,
            totalDebitAmount: (string) ($data['total_debit_amount'] ?? '0'),
            feeRuleId: isset($data['fee_rule_id']) ? (int) $data['fee_rule_id'] : null,
            feeSnapshot: isset($data['fee_snapshot']) && is_array($data['fee_snapshot']) && $data['fee_snapshot'] !== []
                ? new WithdrawalFeeSnapshot(
                    feeRuleId: (string) ($data['fee_snapshot']['fee_rule_id'] ?? ''),
                    feeType: (string) ($data['fee_snapshot']['fee_type'] ?? 'fixed'),
                    fee: (string) ($data['fee_snapshot']['fee'] ?? '0'),
                    minAmount: $data['fee_snapshot']['min_amount'] ?? null,
                    maxAmount: $data['fee_snapshot']['max_amount'] ?? null,
                    priority: isset($data['fee_snapshot']['priority']) ? (int) $data['fee_snapshot']['priority'] : null,
                    metadata: (array) ($data['fee_snapshot']['metadata'] ?? []),
                )
                : null,
            ledgerHoldId: isset($data['ledger_hold_id']) ? (int) $data['ledger_hold_id'] : null,
            reserveOperationId: $data['reserve_operation_id'] ?? null,
            consumeOperationId: $data['consume_operation_id'] ?? null,
            releaseOperationId: $data['release_operation_id'] ?? null,
            reversalOperationId: $data['reversal_operation_id'] ?? null,
            systemWalletId: isset($data['system_wallet_id']) ? (int) $data['system_wallet_id'] : null,
            txid: ! empty($data['txid']) ? new TxId((string) $data['txid']) : null,
            broadcastAttempts: (int) ($data['broadcast_attempts'] ?? 0),
            status: (string) ($data['status'] ?? WithdrawalStatus::REQUESTED),
            requestedAt: $data['requested_at'] ?? null,
            reservedAt: $data['reserved_at'] ?? null,
            broadcastedAt: $data['broadcasted_at'] ?? null,
            settledAt: $data['settled_at'] ?? null,
            confirmedAt: $data['confirmed_at'] ?? null,
            cancelledAt: $data['cancelled_at'] ?? null,
            failedAt: $data['failed_at'] ?? null,
            releasedAt: $data['released_at'] ?? null,
            reorgedAt: $data['reorged_at'] ?? null,
            reversedAt: $data['reversed_at'] ?? null,
            failureReason: $data['failure_reason'] ?? null,
            cancellationReason: $data['cancellation_reason'] ?? null,
            rejectionReason: $data['rejection_reason'] ?? null,
            reorgReason: $data['reorg_reason'] ?? null,
            reversalReason: $data['reversal_reason'] ?? null,
            lastError: $data['last_error'] ?? null,
            confirmedBlockNumber: isset($data['confirmed_block_number']) ? (int) $data['confirmed_block_number'] : null,
            confirmedBlockHash: $data['confirmed_block_hash'] ?? null,
            confirmedConfirmations: isset($data['confirmed_confirmations']) ? (int) $data['confirmed_confirmations'] : null,
            reorgBlockNumber: isset($data['reorg_block_number']) ? (int) $data['reorg_block_number'] : null,
            reversalAttempts: (int) ($data['reversal_attempts'] ?? 0),
            reversalLastError: $data['reversal_last_error'] ?? null,
            reversalFailedAt: $data['reversal_failed_at'] ?? null,
            idempotencyKey: (string) ($data['idempotency_key'] ?? ''),
            version: (int) ($data['version'] ?? 0),
            metadata: (array) ($data['metadata'] ?? []),
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
    public function totalDebitAmount(): string { return $this->totalDebitAmount; }
    public function feeRuleId(): ?int { return $this->feeRuleId; }
    public function feeSnapshot(): ?WithdrawalFeeSnapshot { return $this->feeSnapshot; }
    public function ledgerHoldId(): ?int { return $this->ledgerHoldId; }
    public function reserveOperationId(): ?string { return $this->reserveOperationId; }
    public function consumeOperationId(): ?string { return $this->consumeOperationId; }
    public function releaseOperationId(): ?string { return $this->releaseOperationId; }
    public function reversalOperationId(): ?string { return $this->reversalOperationId; }
    public function systemWalletId(): ?int { return $this->systemWalletId; }
    public function txid(): ?TxId { return $this->txid; }
    public function broadcastAttempts(): int { return $this->broadcastAttempts; }
    public function status(): string { return $this->status; }
    public function requestedAt(): ?string { return $this->requestedAt; }
    public function reservedAt(): ?string { return $this->reservedAt; }
    public function broadcastedAt(): ?string { return $this->broadcastedAt; }
    public function settledAt(): ?string { return $this->settledAt; }
    public function confirmedAt(): ?string { return $this->confirmedAt; }
    public function cancelledAt(): ?string { return $this->cancelledAt; }
    public function failedAt(): ?string { return $this->failedAt; }
    public function releasedAt(): ?string { return $this->releasedAt; }
    public function reorgedAt(): ?string { return $this->reorgedAt; }
    public function reversedAt(): ?string { return $this->reversedAt; }
    public function failureReason(): ?string { return $this->failureReason; }
    public function cancellationReason(): ?string { return $this->cancellationReason; }
    public function rejectionReason(): ?string { return $this->rejectionReason; }
    public function reorgReason(): ?string { return $this->reorgReason; }
    public function reversalReason(): ?string { return $this->reversalReason; }
    public function lastError(): ?string { return $this->lastError; }
    public function confirmedBlockNumber(): ?int { return $this->confirmedBlockNumber; }
    public function confirmedBlockHash(): ?string { return $this->confirmedBlockHash; }
    public function confirmedConfirmations(): ?int { return $this->confirmedConfirmations; }
    public function reorgBlockNumber(): ?int { return $this->reorgBlockNumber; }
    public function reversalAttempts(): int { return $this->reversalAttempts; }
    public function reversalLastError(): ?string { return $this->reversalLastError; }
    public function reversalFailedAt(): ?string { return $this->reversalFailedAt; }
    public function idempotencyKey(): string { return $this->idempotencyKey; }
    public function version(): int { return $this->version; }
    public function metadata(): array { return $this->metadata; }

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
        ?WithdrawalFeeSnapshot $feeSnapshot,
        string $idempotencyKey,
        array $metadata = []
    ): self {
        $self = new self(
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
            totalDebitAmount: $totalDebitAmount,
            feeRuleId: $feeRuleId,
            feeSnapshot: $feeSnapshot,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata
        );

        $self->recordThat(new WithdrawalRequested(
            withdrawalId: null,
            userId: $userId,
            networkId: $networkId,
            currencyNetworkId: $currencyNetworkId,
            idempotencyKey: $idempotencyKey,
            amount: $amount->value(),
        ));

        return $self;
    }

    public function markReserved(int $holdId, string $reserveOperationId): void
    {
        $this->assertCanTransition([WithdrawalStatus::REQUESTED, WithdrawalStatus::RESERVED]);

        $this->status = WithdrawalStatus::RESERVED;
        $this->ledgerHoldId = $holdId;
        $this->reserveOperationId = $reserveOperationId;
        $this->reservedAt = now()->toDateTimeString();
        $this->version++;

        $this->recordThat(new WithdrawalReserved(
            withdrawalId: $this->id?->value() ?? 0,
            ledgerHoldId: $holdId,
            reserveOperationId: $reserveOperationId,
        ));

        $this->assertInvariant();
    }

    public function markBroadcastPending(): void
    {
        $this->assertCanTransition([WithdrawalStatus::RESERVED, WithdrawalStatus::BROADCAST_PENDING]);

        $this->status = WithdrawalStatus::BROADCAST_PENDING;
        $this->version++;
        $this->assertInvariant();
    }

    public function markBroadcasted(TxId $txid, int $systemWalletId, ?string $networkFeeActualAmount = null): void
    {
        $this->assertCanTransition([WithdrawalStatus::RESERVED, WithdrawalStatus::BROADCAST_PENDING, WithdrawalStatus::BROADCASTED]);

        $this->status = WithdrawalStatus::BROADCASTED;
        $this->txid = $txid;
        $this->systemWalletId = $systemWalletId;
        $this->networkFeeActualAmount = $networkFeeActualAmount;
        $this->broadcastAttempts++;
        $this->broadcastedAt = now()->toDateTimeString();
        $this->version++;

        $this->recordThat(new WithdrawalBroadcasted(
            withdrawalId: $this->id?->value() ?? 0,
            txid: $txid->value(),
            systemWalletId: $systemWalletId,
        ));

        $this->assertInvariant();
    }

    public function markSettled(string $consumeOperationId): void
    {
        $this->assertCanTransition([WithdrawalStatus::BROADCASTED, WithdrawalStatus::SETTLED]);

        $this->status = WithdrawalStatus::SETTLED;
        $this->consumeOperationId = $consumeOperationId;
        $this->settledAt = now()->toDateTimeString();
        $this->version++;

        $this->recordThat(new WithdrawalSettled(
            withdrawalId: $this->id?->value() ?? 0,
            consumeOperationId: $consumeOperationId,
        ));

        $this->assertInvariant();
    }

    public function markConfirmed(int $confirmations, ?int $blockNumber = null, ?string $blockHash = null): void
    {
        $this->assertCanTransition([
            WithdrawalStatus::SETTLED,
            WithdrawalStatus::CONFIRMED,
        ]);

        $this->status = WithdrawalStatus::CONFIRMED;
        $this->confirmedAt = now()->toDateTimeString();
        $this->confirmedConfirmations = $confirmations;
        $this->confirmedBlockNumber = $blockNumber;
        $this->confirmedBlockHash = $blockHash;
        $this->version++;

        $this->recordThat(new WithdrawalConfirmed(
            withdrawalId: $this->id?->value() ?? 0,
            txid: $this->txid?->value() ?? '',
            confirmations: $confirmations,
            blockNumber: $blockNumber,
            blockHash: $blockHash,
        ));

        $this->assertInvariant();
    }

    public function markCancelled(string $reason): void
    {
        $this->assertCanTransition([
            WithdrawalStatus::REQUESTED,
            WithdrawalStatus::RESERVED,
            WithdrawalStatus::BROADCAST_PENDING,
        ]);

        $this->status = WithdrawalStatus::CANCELLED;
        $this->cancellationReason = $reason;
        $this->cancelledAt = now()->toDateTimeString();
        $this->version++;

        $this->recordThat(new WithdrawalCancelled(
            withdrawalId: $this->id?->value() ?? 0,
            reason: $reason,
        ));

        $this->assertInvariant();
    }

    public function markFailed(string $reason, ?string $lastError = null): void
    {
        if (in_array($this->status, [WithdrawalStatus::SETTLED, WithdrawalStatus::CONFIRMED, WithdrawalStatus::REVERSED], true)) {
            throw new DomainException('Settled/confirmed/reversed withdrawal cannot be failed.');
        }

        $this->status = WithdrawalStatus::FAILED;
        $this->failureReason = $reason;
        $this->lastError = $lastError;
        $this->failedAt = now()->toDateTimeString();
        $this->version++;

        $this->recordThat(new WithdrawalFailed(
            withdrawalId: $this->id?->value() ?? 0,
            reason: $reason,
            lastError: $lastError,
        ));

        $this->assertInvariant();
    }

    public function markReleased(string $releaseOperationId): void
    {
        $this->assertCanTransition([
            WithdrawalStatus::RESERVED,
            WithdrawalStatus::BROADCAST_PENDING,
        ]);

        $this->status = WithdrawalStatus::RELEASED;
        $this->releaseOperationId = $releaseOperationId;
        $this->releasedAt = now()->toDateTimeString();
        $this->version++;

        $this->recordThat(new WithdrawalReleased(
            withdrawalId: $this->id?->value() ?? 0,
            releaseOperationId: $releaseOperationId,
        ));

        $this->assertInvariant();
    }

    public function markReorged(string $reason, ?int $reorgBlockNumber = null): void
    {
        $this->assertCanTransition([
            WithdrawalStatus::BROADCASTED,
            WithdrawalStatus::SETTLED,
            WithdrawalStatus::CONFIRMED,
        ]);

        $this->status = WithdrawalStatus::REORGED;
        $this->reorgReason = $reason;
        $this->reorgBlockNumber = $reorgBlockNumber;
        $this->reorgedAt = now()->toDateTimeString();
        $this->version++;

        $this->recordThat(new WithdrawalReorged(
            withdrawalId: $this->id?->value() ?? 0,
            reason: $reason,
            reorgBlockNumber: $reorgBlockNumber,
        ));

        $this->assertInvariant();
    }

    public function markReversed(string $reason, string $reversalOperationId): void
    {
        $this->assertCanTransition([
            WithdrawalStatus::REORGED,
            WithdrawalStatus::SETTLED,
            WithdrawalStatus::CONFIRMED,
        ]);

        $this->status = WithdrawalStatus::REVERSED;
        $this->reversalReason = $reason;
        $this->reversalOperationId = $reversalOperationId;
        $this->reversedAt = now()->toDateTimeString();
        $this->version++;

        $this->recordThat(new WithdrawalReversed(
            withdrawalId: $this->id?->value() ?? 0,
            reason: $reason,
            reversalOperationId: $reversalOperationId,
        ));

        $this->assertInvariant();
    }

    public function incrementReversalAttempts(string $error): void
    {
        $this->reversalAttempts++;
        $this->reversalLastError = $error;
        $this->reversalFailedAt = now()->toDateTimeString();
        $this->version++;
    }

    public function recordLastError(string $lastError): void
    {
        $this->lastError = $lastError;
        $this->version++;
        $this->assertInvariant();
    }

    public function withId(int $id): self
    {
        $clone = clone $this;
        $clone->id = new WithdrawalId($id);

        return $clone;
    }

    public function pullDomainEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    private function recordThat(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    private function assertCanTransition(array $allowedStatuses): void
    {
        if (! in_array($this->status, $allowedStatuses, true)) {
            throw new DomainException("Invalid withdrawal transition from [$this->status].");
        }
    }

    private function assertInvariant(): void
    {
        if ($this->userId <= 0 || $this->networkId <= 0 || $this->currencyNetworkId <= 0) {
            throw new DomainException('Foreign keys must be positive.');
        }

        if (bccomp($this->amount->value(), '0', 18) <= 0) {
            throw new DomainException('Withdrawal amount must be greater than zero.');
        }

        if (bccomp($this->feeAmount, '0', 18) < 0) {
            throw new DomainException('feeAmount cannot be negative.');
        }

        if ($this->networkFeeEstimatedAmount !== null && bccomp($this->networkFeeEstimatedAmount, '0', 18) < 0) {
            throw new DomainException('networkFeeEstimatedAmount cannot be negative.');
        }

        if ($this->networkFeeActualAmount !== null && bccomp($this->networkFeeActualAmount, '0', 18) < 0) {
            throw new DomainException('networkFeeActualAmount cannot be negative.');
        }

        if (bccomp($this->totalDebitAmount, '0', 18) <= 0) {
            throw new DomainException('totalDebitAmount must be greater than zero.');
        }
    }
}
