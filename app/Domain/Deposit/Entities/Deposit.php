<?php

declare(strict_types=1);

namespace App\Domain\Deposit\Entities;

use App\Domain\Deposit\Events\DepositConfirmed;
use App\Domain\Deposit\Events\DepositCredited;
use App\Domain\Deposit\Events\DepositDetected;
use App\Domain\Deposit\Events\DepositFailed;
use App\Domain\Deposit\Events\DepositReorged;
use App\Domain\Deposit\Exceptions\DepositAlreadyCredited;
use App\Domain\Deposit\Exceptions\InvalidDepositTransition;
use App\Domain\Deposit\ValueObjects\ConfirmationRequirement;
use App\Domain\Deposit\ValueObjects\DepositId;
use App\Domain\Deposit\ValueObjects\DepositStatus;
use App\Domain\Shared\RecordsDomainEvents;
use App\Domain\Shared\ValueObjects\BlockNumber;
use App\Domain\Shared\ValueObjects\ExternalKey;
//use App\Domain\Shared\ValueObjects\TransactionHash;
use App\Domain\Shared\ValueObjects\TxId;

final class Deposit
{
    use RecordsDomainEvents;

    /** @var array<object> */
    //private array $domainEvents = [];

    private function __construct(
        private ?DepositId $id,
        private int $userId,
        private int $networkId,
        private int $currencyNetworkId,
        private int $walletAddressId,
        private ExternalKey $externalKey,
        private TxId $txid,
        private string $amount,
        private string $toAddress,
        private DepositStatus $status,
        private ?string $fromAddress = null,
        //denis this must throw out from Deposit entity
        private ?string $blockHash = null,
        private ?BlockNumber $blockNumber = null,
        private int $confirmations = 0,
        private ?\DateTimeImmutable $detectedAt = null,
        private ?\DateTimeImmutable $confirmedAt = null,
        private ?\DateTimeImmutable $creditedAt = null,
        private ?\DateTimeImmutable $finalizedAt = null,
        private ?\DateTimeImmutable $failedAt = null,
        private ?string $failureReason = null,
        private array $metadata = [],
        // ledger linkage
        private ?string $creditedOperationId = null,
        private ?string $reversalOperationId = null,
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
        $this->assertInvariant();
    }

    public static function detect(
        int $userId,
        int $networkId,
        int $currencyNetworkId,
        int $walletAddressId,
        ExternalKey $externalKey,
        TxId $txid,
        string $amount,
        string $toAddress,
        ?string $fromAddress = null,
        ?string $blockHash = null,
        ?BlockNumber $blockNumber = null,
        int $confirmations = 0,
        array $metadata = []
    ): self {
        $deposit = new self(
            id: null,
            userId: $userId,
            networkId: $networkId,
            currencyNetworkId: $currencyNetworkId,
            walletAddressId: $walletAddressId,
            externalKey: $externalKey,
            txid: $txid,
            amount: $amount,
            toAddress: $toAddress,
            status: DepositStatus::Detected,
            fromAddress: $fromAddress,
            blockHash: $blockHash,
            blockNumber: $blockNumber,
            confirmations: $confirmations,
            detectedAt: new \DateTimeImmutable(),
            metadata: $metadata,
        );

        $deposit->recordDomainEvent(new DepositDetected(
            depositId: null,
            networkId: $networkId,
            externalKey: $externalKey->value(),
            txid: $txid->value(),
            amount: $amount,
        ));

        return $deposit;
    }

    public static function hydrate(
        int $id,
        int $userId,
        int $networkId,
        int $currencyNetworkId,
        int $walletAddressId,
        ExternalKey $externalKey,
        TxId $txid,
        string $amount,
        string $toAddress,
        DepositStatus $status,
        ?string $fromAddress = null,
        ?string $blockHash = null,
        ?BlockNumber $blockNumber = null,
        int $confirmations = 0,
        ?\DateTimeImmutable $detectedAt = null,
        ?\DateTimeImmutable $confirmedAt = null,
        ?\DateTimeImmutable $creditedAt = null,
        ?\DateTimeImmutable $finalizedAt = null,
        ?\DateTimeImmutable $failedAt = null,
        ?string $failureReason = null,
        array $metadata = [],
        // Ledger lifecycle
        ?string $creditedOperationId = null,
        ?string $reversalOperationId = null,
        ?\DateTimeImmutable $reorgedAt = null,
        ?\DateTimeImmutable $reversedAt = null,
        ?string $reorgReason = null,
        ?string $reversalReason = null,
        ?int $reorgBlockNumber = null,
        int $reversalAttempts = 0,
        ?string $reversalLastError = null,
        ?\DateTimeImmutable $reversalFailedAt = null,
    ): self {
        return new self(
            id: DepositId::fromString($id),
            userId: $userId,
            networkId: $networkId,
            currencyNetworkId: $currencyNetworkId,
            walletAddressId: $walletAddressId,
            externalKey: $externalKey,
            txid: $txid,
            amount: $amount,
            toAddress: $toAddress,
            status: $status,
            fromAddress: $fromAddress,
            blockHash: $blockHash,
            blockNumber: $blockNumber,
            confirmations: $confirmations,
            detectedAt: $detectedAt,
            confirmedAt: $confirmedAt,
            creditedAt: $creditedAt,
            finalizedAt: $finalizedAt,
            failedAt: $failedAt,
            failureReason: $failureReason,
            metadata: $metadata,
            creditedOperationId: $creditedOperationId,
            reversalOperationId: $reversalOperationId,
            reorgedAt: $reorgedAt,
            reversedAt: $reversedAt,
            reorgReason: $reorgReason,
            reversalReason: $reversalReason,
            reorgBlockNumber: $reorgBlockNumber,
            reversalAttempts: $reversalAttempts,
            reversalLastError: $reversalLastError,
            reversalFailedAt: $reversalFailedAt,
        );
    }

    public function assignId(DepositId $id): void
    {
        if ($this->id !== null) {
            return;
        }

        $this->id = $id;
    }

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

    public function markPending(): void
    {
        // нельзя уводить из terminal states.
        if (in_array($this->status, [DepositStatus::Credited, DepositStatus::Reversed, DepositStatus::Failed], true)) {
            throw new InvalidDepositTransition($this->status->value, DepositStatus::Pending->value);
        }

        $this->status = DepositStatus::Pending;
    }

    public function markConfirmed(): void
    {
        //запрещаем подтверждать terminal states.
        if (in_array($this->status, [DepositStatus::Credited, DepositStatus::Reversed, DepositStatus::Failed], true)) {
            throw new InvalidDepositTransition($this->status->value, DepositStatus::Confirmed->value);
        }

        if ($this->confirmations < 1 && $this->finalizedAt === null) {
            throw new InvalidDepositTransition($this->status->value, DepositStatus::Confirmed->value);
        }

        if ($this->status === DepositStatus::Confirmed) {
            return;
        }

        $this->status = DepositStatus::Confirmed;
        $this->confirmedAt = new \DateTimeImmutable();

        $this->recordDomainEvent(new DepositConfirmed(
            depositId: $this->id?->value(),
            networkId: $this->networkId,
            externalKey: $this->externalKey->value(),
            txid: $this->txid?->value() ?? '',
        ));
    }

    public function markCredited(string $operationId): void
    {
        if ($this->status === DepositStatus::Credited) {
            throw new DepositAlreadyCredited();
        }

        if ($this->status !== DepositStatus::Confirmed) {
            throw new InvalidDepositTransition($this->status->value, DepositStatus::Credited->value);
        }

        $this->status = DepositStatus::Credited;
        $this->creditedAt = new \DateTimeImmutable();

        // DepositCredited + фиксируем ещё и связь с ledger operation.
        $this->creditedOperationId = $operationId;

        $this->recordDomainEvent(new DepositCredited(
            depositId: $this->id?->value(),
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
     * - reorged deposit не означает reversed deposit.
     *
     * - если deposit уже credited, этот метод только помечает его как reorged;
     * - reversal позже делает LedgerService.
     */
    public function markReorged(
        ?BlockNumber $rewindToBlock = null,
        ?string $reason = 'blockchain_reorg'
    ): void {
        $wasCredited = $this->status === DepositStatus::Credited;
        $oldBlock = $this->blockNumber?->value();

        $this->status = DepositStatus::Reorged;
        $this->reorgedAt = new \DateTimeImmutable();
        $this->reorgReason = $reason;
        $this->reorgBlockNumber = $rewindToBlock?->value();
        // СБРОС текущих on-chain индикаторов, потому что chain state был откатан.
        $this->blockHash = null;
        $this->blockNumber = null;
        $this->confirmations = 0;
        $this->finalizedAt = null;
        // creditedAt НЕ трогаем: это факт, что ledger когда-то кредитовал.
        // Его потом компенсирует reversal.
        if ($wasCredited) {
            $this->recordDomainEvent(new DepositReorged(
                depositId: $this->id?->value(),
                networkId: $this->networkId,
                externalKey: $this->externalKey->value(),
                oldBlockNumber: $oldBlock,
                newBlockNumber: $rewindToBlock?->value(),
            ));
        }
    }

    public function markReversed(string $operationId, ?string $reason = 'deposit_reversal'): void
    {
        if (! in_array($this->status, [DepositStatus::Reorged, DepositStatus::Credited], true)) {
            throw new InvalidDepositTransition($this->status->value, DepositStatus::Reversed->value);
        }

        $this->status = DepositStatus::Reversed;
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
        $this->status = DepositStatus::ReversalFailed;
    }

    public function markFailed(string $reason): void
    {
        if ($this->status === DepositStatus::Credited) {
            throw new InvalidDepositTransition($this->status->value, DepositStatus::Failed->value);
        }

        $this->status = DepositStatus::Failed;
        $this->failedAt = new \DateTimeImmutable();
        $this->failureReason = $reason;

        $this->recordDomainEvent(new DepositFailed(
            depositId: $this->id?->value(),
            networkId: $this->networkId,
            externalKey: $this->externalKey->value(),
            reason: $reason,
        ));
    }

    /**
     * business rule:
     * - confirmed
     * - enough confirmations or finality
     * - not already credited
     *
     * - reorg/reversal states не допускаются в кредит;
     * - terminal reversal states тоже запрещены.
     */
    public function canBeCredited(ConfirmationRequirement $requirement): bool
    {
        if (
            in_array($this->status, [
                DepositStatus::Credited,
                DepositStatus::Failed,
                DepositStatus::Reversed,
                DepositStatus::ReversalFailed,
            ], true)
        ) {
            return false;
        }

        if ($requirement->isBlocks()) {
            return $this->status === DepositStatus::Confirmed
                && $this->confirmations >= $requirement->requiredConfirmations;
        }

        if ($requirement->isFinality()) {
            return $this->status === DepositStatus::Confirmed
                && $this->finalizedAt !== null;
        }

        return false;
    }

    public function isOpen(): bool
    {
        // reorged/reversed/reversal_failed — уже не open.
        return in_array(
            $this->status,
            [DepositStatus::Detected, DepositStatus::Pending, DepositStatus::Confirmed],
            true
        );
    }

    public function isCredited(): bool
    {
        return $this->status === DepositStatus::Credited;
    }

    /**
     * Удобная проверка для recovery/reversal workflow.
     */
    public function needsReversal(): bool
    {
        return $this->status === DepositStatus::Reorged
            && $this->creditedOperationId !== null
            && $this->reversalOperationId === null;
    }

    public function id(): ?DepositId { return $this->id; }
    public function userId(): int { return $this->userId; }
    public function networkId(): int { return $this->networkId; }
    public function currencyNetworkId(): int { return $this->currencyNetworkId; }
    public function walletAddressId(): int { return $this->walletAddressId; }
    public function externalKey(): ExternalKey { return $this->externalKey; }
    public function txid(): TxId { return $this->txid; }
    public function amount(): string { return $this->amount; }
    public function toAddress(): string { return $this->toAddress; }
    public function fromAddress(): ?string { return $this->fromAddress; }
    public function blockHash(): ?string { return $this->blockHash; }
    public function blockNumber(): ?BlockNumber { return $this->blockNumber; }
    public function confirmations(): int { return $this->confirmations; }
    public function status(): DepositStatus { return $this->status; }
    public function detectedAt(): ?\DateTimeImmutable { return $this->detectedAt; }
    public function confirmedAt(): ?\DateTimeImmutable { return $this->confirmedAt; }
    public function creditedAt(): ?\DateTimeImmutable { return $this->creditedAt; }
    public function finalizedAt(): ?\DateTimeImmutable { return $this->finalizedAt; }
    public function failedAt(): ?\DateTimeImmutable { return $this->failedAt; }
    public function failureReason(): ?string { return $this->failureReason; }
    public function metadata(): array { return $this->metadata; }

    public function creditedOperationId(): ?string { return $this->creditedOperationId; }
    public function reversalOperationId(): ?string { return $this->reversalOperationId; }
    public function reorgedAt(): ?\DateTimeImmutable { return $this->reorgedAt; }
    public function reversedAt(): ?\DateTimeImmutable { return $this->reversedAt; }
    public function reorgReason(): ?string { return $this->reorgReason; }
    public function reversalReason(): ?string { return $this->reversalReason; }
    public function reorgBlockNumber(): ?int { return $this->reorgBlockNumber; }
    public function reversalAttempts(): int { return $this->reversalAttempts; }
    public function reversalLastError(): ?string { return $this->reversalLastError; }
    public function reversalFailedAt(): ?\DateTimeImmutable { return $this->reversalFailedAt; }

    private function assertInvariant(): void
    {
        //amount всегда положительный decimal-string
        if (! is_numeric($this->amount) || bccomp($this->amount, '0', 18) < 0) {
            throw new \DomainException('Deposit amount must be a non-negative numeric string.');
        }
    }
}
