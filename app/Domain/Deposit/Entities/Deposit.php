<?php

namespace App\Domain\Deposit\Entities;

use App\Domain\Deposit\Events\DepositConfirmed;
use App\Domain\Deposit\Events\DepositCredited;
use App\Domain\Deposit\Events\DepositDetected;
use App\Domain\Deposit\Events\DepositFailed;
use App\Domain\Deposit\Events\DepositReorged;
use App\Domain\Deposit\Exceptions\DepositAlreadyCredited;
use App\Domain\Deposit\Exceptions\InvalidDepositTransition;
use App\Domain\Deposit\ValueObjects\BlockNumber;
use App\Domain\Deposit\ValueObjects\ConfirmationRequirement;
use App\Domain\Deposit\ValueObjects\DepositId;
use App\Domain\Deposit\ValueObjects\DepositStatus;
use App\Domain\Deposit\ValueObjects\ExternalKey;
use App\Domain\Deposit\ValueObjects\TransactionHash;

/**
 * Deposit — aggregate root.
 *
 * Снаружи никто не должен менять его поля напрямую.
 * Все изменения идут через методы, которые защищают инварианты.
 */
final class Deposit
{
    /** @var array<object> */
    private array $domainEvents = [];

    private function __construct(
        private ?DepositId $id,
        private int $userId,
        private int $currencyId,
        private int $networkId,
        private int $currencyNetworkId,
        private int $walletAddressId,
        private ExternalKey $externalKey,
        private TransactionHash $txid,
        private string $amount,
        private string $toAddress,
        private DepositStatus $status,
        private ?string $fromAddress = null,
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
    ) {}

    public static function detect(
        int $userId,
        int $currencyId,
        int $networkId,
        int $currencyNetworkId,
        int $walletAddressId,
        ExternalKey $externalKey,
        TransactionHash $txid,
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
            currencyId: $currencyId,
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

        $deposit->record(new DepositDetected(
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
        int $currencyId,
        int $networkId,
        int $currencyNetworkId,
        int $walletAddressId,
        ExternalKey $externalKey,
        TransactionHash $txid,
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
    ): self {
        return new self(
            id: new DepositId($id),
            userId: $userId,
            currencyId: $currencyId,
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
     * Обновляет on-chain evidence без ломания инвариантов.
     * Нормально вызывается scanner/webhook adapter'ом.
     */
    public function syncEvidence(
        ?string $fromAddress = null,
        ?string $toAddress = null,
        ?string $blockHash = null,
        ?BlockNumber $blockNumber = null,
        ?int $confirmations = null,
        ?\DateTimeImmutable $finalizedAt = null,
        ?array $metadata = null
    ): void {
        if ($fromAddress !== null) {
            $this->fromAddress = $fromAddress;
        }

        if ($toAddress !== null) {
            $this->toAddress = $toAddress;
        }

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
        if ($this->status === DepositStatus::Credited) {
            throw new InvalidDepositTransition($this->status->value, DepositStatus::Pending->value);
        }

        $this->status = DepositStatus::Pending;
    }

    public function markConfirmed(): void
    {
        if ($this->status === DepositStatus::Credited) {
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

        $this->record(new DepositConfirmed(
            depositId: $this->id?->value(),
            networkId: $this->networkId,
            externalKey: $this->externalKey->value(),
            txid: $this->txid->value(),
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

        $this->record(new DepositCredited(
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

    public function markReorged(?BlockNumber $newBlockNumber = null): void
    {
        $oldBlock = $this->blockNumber?->value();

        $this->status = DepositStatus::Reorged;
        $this->blockNumber = $newBlockNumber;
        $this->confirmations = 0;
        $this->finalizedAt = null;

        $this->record(new DepositReorged(
            depositId: $this->id?->value(),
            networkId: $this->networkId,
            externalKey: $this->externalKey->value(),
            oldBlockNumber: $oldBlock,
            newBlockNumber: $newBlockNumber?->value(),
        ));
    }

    public function markFailed(string $reason): void
    {
        $this->status = DepositStatus::Failed;
        $this->failedAt = new \DateTimeImmutable();
        $this->failureReason = $reason;

        $this->record(new DepositFailed(
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
     */
    public function canBeCredited(ConfirmationRequirement $requirement): bool
    {
        if ($this->status === DepositStatus::Credited || $this->status === DepositStatus::Failed) {
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
        return in_array($this->status, [DepositStatus::Detected, DepositStatus::Pending, DepositStatus::Confirmed], true);
    }

    public function isCredited(): bool
    {
        return $this->status === DepositStatus::Credited;
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    private function record(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    public function id(): ?DepositId { return $this->id; }
    public function userId(): int { return $this->userId; }
    public function currencyId(): int { return $this->currencyId; }
    public function networkId(): int { return $this->networkId; }
    public function currencyNetworkId(): int { return $this->currencyNetworkId; }
    public function walletAddressId(): int { return $this->walletAddressId; }
    public function externalKey(): ExternalKey { return $this->externalKey; }
    public function txid(): TransactionHash { return $this->txid; }
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
}
