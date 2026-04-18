<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Entities;

use App\Domain\Deposit\ValueObjects\ConfirmationRequirement;
use App\Domain\Shared\RecordsDomainEvents;
use App\Domain\Shared\ValueObjects\Amount;
use App\Domain\Shared\ValueObjects\BlockNumber;
use App\Domain\Shared\ValueObjects\ExternalKey;
use App\Domain\Shared\ValueObjects\TxId;
use App\Domain\Withdrawal\Events\WithdrawalBroadcasted;
use App\Domain\Withdrawal\Events\WithdrawalCancelled;
use App\Domain\Withdrawal\Events\WithdrawalConfirmed;
use App\Domain\Withdrawal\Events\WithdrawalDebited;
use App\Domain\Withdrawal\Events\WithdrawalFailed;
use App\Domain\Withdrawal\Events\WithdrawalReleased;
use App\Domain\Withdrawal\Events\WithdrawalReorged;
use App\Domain\Withdrawal\Events\WithdrawalRequested;
use App\Domain\Withdrawal\Events\WithdrawalReserved;
use App\Domain\Withdrawal\Events\WithdrawalSettled;
use App\Domain\Withdrawal\Exceptions\InvalidWithdrawalTransition;
use App\Domain\Withdrawal\Exceptions\WithdrawalAlreadyDebited;
use App\Domain\Withdrawal\ValueObjects\WithdrawalAddress;
use App\Domain\Withdrawal\ValueObjects\WithdrawalFeeSnapshot;
use App\Domain\Withdrawal\ValueObjects\WithdrawalId;
use App\Domain\Withdrawal\ValueObjects\WithdrawalStatus;
use App\Domain\Withdrawal\ValueObjects\WithdrawalTag;
use DomainException;

/**
 * CHANGED:
 * - withdrawal is now the process aggregate root;
 * - domain events are recorded locally;
 * - no outbox bridge is required between internal steps.
 */
final class Withdrawal
{
    use RecordsDomainEvents;

    private function __construct(
        private ?WithdrawalId          $id,
        private int                    $userId,
        private int                    $networkId,
        private int                    $currencyNetworkId,
        private WithdrawalAddress      $destinationAddress,
        private ?WithdrawalTag         $destinationTag,
        private Amount                 $amount,
        private ExternalKey            $externalKey,
        private string                 $feeAmount,
        private ?string                $networkFeeEstimatedAmount,
        private ?string                $networkFeeActualAmount,
        private string                 $totalDebitAmount,
        private ?int                   $feeRuleId = null,
        private ?WithdrawalFeeSnapshot $feeSnapshot = null,
        private ?int                   $ledgerHoldId = null,
        private ?string                $reserveOperationId = null,
        private ?string                $consumeOperationId = null,
        private ?string                $releaseOperationId = null,
        private ?int                   $systemWalletId = null,
        private ?TxId                  $txid = null,

        //denis this must throw out from Withdrawal entity
        private ?string                $blockHash = null,//denis
        private ?BlockNumber           $blockNumber = null,//denis
        private int                    $broadcastAttempts = 0,
        private int                    $confirmations = 0,//denis
        private string                 $status = WithdrawalStatus::REQUESTED,
        private ?\DateTimeImmutable    $requestedAt = null,
        private ?\DateTimeImmutable    $reservedAt = null,
        private ?\DateTimeImmutable    $broadcastedAt = null,
        private ?\DateTimeImmutable    $settledAt = null,
        private ?\DateTimeImmutable $confirmedAt = null,
        private ?\DateTimeImmutable $debitedAt = null,
        private ?\DateTimeImmutable $finalizedAt = null,//denis
        private ?\DateTimeImmutable $cancelledAt = null,
        private ?\DateTimeImmutable $failedAt = null,
        private ?\DateTimeImmutable $releasedAt = null,
        private ?string $failureReason = null,
        private ?string $cancellationReason = null,
        private ?string $rejectionReason = null,
        private ?string $lastError = null,
        //ledger linkage
        private ?string $debitedOperationId,
        private ?string $reversalOperationId,

        private string $idempotencyKey = '',
        private int $version = 0,
        private array $metadata = [],

        //reorg lifecycle
        private ?\DateTimeImmutable $reorgedAt = null,
        private ?\DateTimeImmutable $reversedAt = null,
        private ?string $reorgReason = null,
        private ?string $reversalReason = null,
        private ?int $reorgBlockNumber = null,
        //retry/incident tracking for reversal
        private int $reversalAttempts = 0,
        private ?string $reversalLastError = null,
        private ?\DateTimeImmutable $reversalFailedAt = null,
    ) {
        $this->status = (new WithdrawalStatus($status))->value();
        $this->assertInvariant();
    }

    public static function request(
        int                    $userId,
        int                    $networkId,
        int                    $currencyNetworkId,
        WithdrawalAddress      $destinationAddress,
        ?WithdrawalTag         $destinationTag,
        Amount                 $amount,
        string                 $feeAmount,
        ?string                $networkFeeEstimatedAmount,
        string                 $totalDebitAmount,
        ?int                   $feeRuleId,
        ?WithdrawalFeeSnapshot $feeSnapshot,
        ExternalKey            $externalKey,
        string                 $idempotencyKey,
        ?string                $blockHash = null,
        ?BlockNumber           $blockNumber = null,
        int                    $confirmations = 0,
        array                  $metadata = [],
    ): self {
        $self = new self(
            id: null,
            userId: $userId,
            networkId: $networkId,
            currencyNetworkId: $currencyNetworkId,
            destinationAddress: $destinationAddress,
            destinationTag: $destinationTag,
            amount: $amount,
            externalKey: $externalKey,
            feeAmount: $feeAmount,
            networkFeeEstimatedAmount: $networkFeeEstimatedAmount,
            networkFeeActualAmount: null,
            totalDebitAmount: $totalDebitAmount,
            ExternalKey: $externalKey,
            feeRuleId: $feeRuleId,
            feeSnapshot: $feeSnapshot,
            blockHash: $blockHash,
            blockNumber: $blockNumber,
            confirmations: $confirmations,
            status: WithdrawalStatus::REQUESTED,
            requestedAt: new \DateTimeImmutable(),
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
        );

        $self->recordDomainEvent(new WithdrawalRequested(
            withdrawalId: null,
            userId: $userId,
            networkId: $networkId,
            currencyNetworkId: $currencyNetworkId,
            idempotencyKey: $idempotencyKey,
            amount: $amount->value(),
        ));

        return $self;
    }


//    public function withId(int $id): self
//    {
//        $clone = clone $this;
//        $clone->id = new WithdrawalId($id);
//
//        return $clone;
//    }
    public function assignId(WithdrawalId $id): void
    {
        if ($this->id !== null) {
            return;
        }

        $this->id = $id;
    }
    public static function hydrate(  //denis
        need to do-----> like Deposit


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
    public function failureReason(): ?string { return $this->failureReason; }
    public function cancellationReason(): ?string { return $this->cancellationReason; }
    public function rejectionReason(): ?string { return $this->rejectionReason; }
    public function lastError(): ?string { return $this->lastError; }
    public function idempotencyKey(): string { return $this->idempotencyKey; }
    public function version(): int { return $this->version; }
    public function metadata(): array { return $this->metadata; }

    /**
     * updateConfirmations:
     * - подтверждения можно обновлять и после reorg, когда депозит был найден заново;
     * - confirmations не уменьшаются;
     * - metadata
     */
    public function updateConfirmations(
        ?string $blockHash = null,
        ?BlockNumber $blockNumber = null,
        ?int $confirmations = null,
        ?\DateTimeImmutable $finalizedAt = null,
        ?array $metadata = null
    ): void {
        if ($blockHash !== null) {
            $this->blockHash = $blockHash;
        }

        if ($blockNumber !== null) {
            $this->blockNumber = $blockNumber;
        }

        if ($confirmations !== null && $confirmations > $this->confirmations) {
            $this->confirmations = $confirmations;
        }

        if ($finalizedAt !== null) {
            $this->finalizedAt = $finalizedAt;
        }

        if ($metadata !== null) {
            $this->metadata = array_replace($this->metadata, $metadata);
        }
    }

    public function markReserved(int $holdId, string $reserveOperationId): void
    {
        $this->assertCanTransition([WithdrawalStatus::REQUESTED, WithdrawalStatus::RESERVED]);

        $this->status = WithdrawalStatus::RESERVED;
        $this->ledgerHoldId = $holdId;
        $this->reserveOperationId = $reserveOperationId;
        $this->reservedAt = new \DateTimeImmutable();//now()->toDateTimeString();
        $this->version++;

        $this->recordDomainEvent(new WithdrawalReserved(
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
        $this->broadcastedAt = new \DateTimeImmutable();//now()->toDateTimeString();
        $this->version++;

        $this->recordDomainEvent(new WithdrawalBroadcasted(
            withdrawalId: $this->id?->value() ?? 0,
            txid: $txid->value(),
            systemWalletId: $systemWalletId,
        ));

        $this->assertInvariant();
    }

    public function markSettled(string $consumeOperationId): void
    {
        $this->assertCanTransition([
            WithdrawalStatus::BROADCASTED,
            WithdrawalStatus::SETTLED,
        ]);

        $this->status = WithdrawalStatus::SETTLED;
        $this->consumeOperationId = $consumeOperationId;
        $this->settledAt = new \DateTimeImmutable();//now()->toDateTimeString();
        $this->version++;

        $this->recordDomainEvent(new WithdrawalSettled(
            withdrawalId: $this->id?->value() ?? 0,
            consumeOperationId: $consumeOperationId,
        ));

        $this->assertInvariant();
    }

    public function markConfirmed(): void
    {
        $this->assertCanTransition([
            WithdrawalStatus::SETTLED,
            //WithdrawalStatus::CONFIRMED,
        ]);

        if ($this->confirmations < 1 && $this->finalizedAt === null) {
            throw new InvalidWithdrawalTransition("Invalid Withdrawal transition to status CONFIRMED, confirmations = {$this->confirmations}, finalizedAt= {$this->finalizedAt}");
        }
        if ($this->status === WithdrawalStatus::CONFIRMED) {
            return;
        }
        $this->status = WithdrawalStatus::CONFIRMED;
        $this->confirmedAt =  new \DateTimeImmutable();//now()->toDateTimeString();
        $this->version++;

        $this->recordDomainEvent(new WithdrawalConfirmed(
            withdrawalId: $this->id?->value() ?? 0,
            networkId: $this->networkId,
            externalKey: $this->externalKey->value(),
            txid: $this->txid?->value() ?? '',
        ));

        $this->assertInvariant();
    }

    public function markDebited(string $operationId): void
    {
        if ($this->status === WithdrawalStatus::DEBITED) {
            throw new WithdrawalAlreadyDebited();
        }

        if ($this->status !== WithdrawalStatus::CONFIRMED) {
            throw new InvalidWithdrawalTransition("Invalid transition Withdrawal must be CONFIRMED");
        }

        $this->status = WithdrawalStatus::DEBITED;
        $this->debitedAt = new \DateTimeImmutable();

        // WithdrawalDebited + фиксируем ещё и связь с ledger operation.
        $this->debitedOperationId = $operationId;

        $this->recordDomainEvent(new WithdrawalDebited(
            WithdrawalId: $this->id?->value(),
            networkId: $this->networkId,
            externalKey: $this->externalKey->value(),
            operationId: $operationId,
        ));
    }
    public function markFinalized(): void
    {
        $this->finalizedAt = new \DateTimeImmutable();
    }

     /**
     * - reorg фиксируется как отдельный lifecycle state;
     * - сохраняем причину reorg;
     * - сохраняем rewind target;
     * - очищаем текущие on-chain признаки;
     * - НЕ удаляем creditedAt/confirmedAt — это история;
     * - reorged withdrawal не означает reversed withdrawal.
     *
     * - если withdrawal уже credited, этот метод только помечает его как reorged;
     * - reversal позже делает LedgerService.
     */
    public function markReorged(
        ?BlockNumber $rewindToBlock = null,
        ?string $reason = 'blockchain_reorg'
    ): void {
        $wasDebited = $this->status === WithdrawalStatus::DEBITED;
        $oldBlock = $this->blockNumber?->value();

        $this->status = WithdrawalStatus::REORGED;
        $this->reorgedAt = new \DateTimeImmutable();
        $this->reorgReason = $reason;
        $this->reorgBlockNumber = $rewindToBlock?->value();
        // СБРОС текущих on-chain индикаторов, потому что chain state был откатан.
        $this->blockHash = null;
        $this->blockNumber = null;
        $this->confirmations = 0;
        $this->finalizedAt = null;
        // debitedAt НЕ трогаем: это факт, что ledger когда-то дебитовал.
        // Его потом компенсирует reversal.
        if ($wasDebited) {
            $this->recordDomainEvent(new WithdrawalReorged(
                WithdrawalId: $this->id?->value(),
                networkId: $this->networkId,
                externalKey: $this->externalKey->value(),
                oldBlockNumber: $oldBlock,
                newBlockNumber: $rewindToBlock?->value(),
            ));
        }
    }

    public function markReversed(string $operationId, ?string $reason = 'withdrawal_reversal'): void
    {
        if (! in_array($this->status, [WithdrawalStatus::REORGED, WithdrawalStatus::DEBITED], true)) {
            throw new InvalidWithdrawalTransition("Withdrawal can be reversed if in REORGED or DEBITED");
        }

        $this->status = WithdrawalStatus::REVERSED;
        $this->reversedAt = new \DateTimeImmutable();
        $this->reversalOperationId = $operationId;
        $this->reversalReason = $reason;
    }

    public function markReversalFailed(string $error): void
    {
        $this->reversalAttempts++;
        $this->reversalLastError = $error;
        $this->reversalFailedAt = new \DateTimeImmutable();

        // Можно оставить статус Reorged и просто копить retry,
        $this->status = WithdrawalStatus::REVERSAL_FAILED;
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
        $this->cancelledAt = new \DateTimeImmutable();//now()->toDateTimeString();
        $this->version++;

        $this->recordDomainEvent(new WithdrawalCancelled(
            withdrawalId: $this->id?->value() ?? 0,
            reason: $reason,
        ));

        $this->assertInvariant();
    }

    public function markFailed(string $reason, ?string $lastError = null): void
    {
        if (in_array($this->status, [WithdrawalStatus::SETTLED, WithdrawalStatus::CONFIRMED], true)) {
            throw new DomainException('Settled withdrawal cannot be failed.');
        }

        $this->status = WithdrawalStatus::FAILED;
        $this->failureReason = $reason;
        $this->lastError = $lastError;
        $this->failedAt = new \DateTimeImmutable();//now()->toDateTimeString();
        $this->version++;

        $this->recordDomainEvent(new WithdrawalFailed(
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
        $this->releasedAt = new \DateTimeImmutable();//now()->toDateTimeString();
        $this->version++;

        $this->recordDomainEvent(new WithdrawalReleased(
            withdrawalId: $this->id?->value() ?? 0,
            releaseOperationId: $releaseOperationId,
        ));

        $this->assertInvariant();
    }

    public function recordLastError(string $lastError): void
    {
        $this->lastError = $lastError;
        $this->version++;
        $this->assertInvariant();
    }
///////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////
     /**
     * business rule:
     * - confirmed
     * - enough confirmations or finality
     * - not already debited
     *
     * - reorg/reversal states не допускаются в дебит;
     * - terminal reversal states тоже запрещены.
     */
    public function canBeDebited(ConfirmationRequirement $requirement): bool
    {
        if (
            in_array($this->status, [
                WithdrawalStatus::DEBITED,
                WithdrawalStatus::FAILED,
                WithdrawalStatus::REVERSED,
                WithdrawalStatus::REVERSAL_FAILED,
                WithdrawalStatus::CANCELLED
            ], true)
        ) {
            return false;
        }

        if ($requirement->isBlocks()) {
            return $this->status === WithdrawalStatus::CONFIRMED
                && $this->confirmations >= $requirement->requiredConfirmations;
        }

        if ($requirement->isFinality()) {
            return $this->status === WithdrawalStatus::CONFIRMED
                && $this->finalizedAt !== null;
        }

        return false;
    }

    public function isOpen(): bool
    {
        // reorged/reversed/reversal_failed — уже не open.
        return in_array(
            $this->status,
            [WithdrawalStatus::REQUESTED, WithdrawalStatus::RESERVED,WithdrawalStatus::SETTLED, WithdrawalStatus::CONFIRMED],
            true
        );
    }

    public function isDebited(): bool
    {
        return $this->status === WithdrawalStatus::DEBITED;
    }

    /**
     * Удобная проверка для recovery/reversal workflow.
     */
    public function needsReversal(): bool
    {
        return $this->status === WithdrawalStatus::REORGED
            && $this->debitedOperationId !== null
            && $this->reversalOperationId === null;
    }
////////////////////////////////////////////////////////////////////////////
    private function assertCanTransition(array $allowedStatuses): void
    {
        if (! in_array($this->status, $allowedStatuses, true)) {
            throw new DomainException("Invalid withdrawal state transition from [$this->status].");
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
