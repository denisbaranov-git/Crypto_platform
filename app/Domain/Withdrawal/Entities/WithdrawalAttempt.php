<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Entities;

use App\Domain\Shared\RecordsDomainEvents;
use App\Domain\Withdrawal\Events\WithdrawalAttemptBroadcasted;
use App\Domain\Withdrawal\Events\WithdrawalAttemptFailed;
use App\Domain\Withdrawal\Events\WithdrawalAttemptConfirmed;
use App\Domain\Withdrawal\Events\WithdrawalAttemptStarted;
use DomainException;

final class WithdrawalAttempt
{
    use RecordsDomainEvents;
    /** @var array<int, object> */
    private array $recordedEvents = [];

    public function __construct(
        private ?int $id,
        private int $withdrawalId,
        private int $attemptNo,
        private string $status = 'pending',
        private ?string $broadcastFingerprint = null,
        private ?string $txid = null,
        private ?string $broadcastDriver = null,
        private ?string $rawTransactionHash = null,
        private ?string $rawTransaction = null,
        private array $requestPayload = [],
        private array $responsePayload = [],
        private ?string $errorMessage = null,
        private ?string $broadcastedAt = null,
        private ?string $confirmedAt = null,
        private ?string $failedAt = null,
    ) {
        $this->assertInvariant();
    }

    public static function hydrate(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            withdrawalId: (int) ($data['withdrawal_id'] ?? 0),
            attemptNo: (int) ($data['attempt_no'] ?? 0),
            status: (string) ($data['status'] ?? 'pending'),
            broadcastFingerprint: $data['broadcast_fingerprint'] ?? null,
            txid: $data['txid'] ?? null,
            broadcastDriver: $data['broadcast_driver'] ?? null,
            rawTransactionHash: $data['raw_transaction_hash'] ?? null,
            rawTransaction: $data['raw_transaction'] ?? null,
            requestPayload: (array) ($data['request_payload'] ?? []),
            responsePayload: (array) ($data['response_payload'] ?? []),
            errorMessage: $data['error_message'] ?? null,
            broadcastedAt: $data['broadcasted_at'] ?? null,
            confirmedAt: $data['confirmed_at'] ?? null,
            failedAt: $data['failed_at'] ?? null,
        );
    }

    public static function start(
        int $withdrawalId,
        int $attemptNo,
        ?string $broadcastDriver,
        array $requestPayload = []
    ): self {
        $self = new self(
            id: null,
            withdrawalId: $withdrawalId,
            attemptNo: $attemptNo,
            status: 'broadcasting',
            broadcastDriver: $broadcastDriver,
            requestPayload: $requestPayload,
        );

        $self->recordDomainEvent(new WithdrawalAttemptStarted(
            withdrawalId: $withdrawalId,
            attemptNo: $attemptNo,
            broadcastDriver: $broadcastDriver,
        ));

        return $self;
    }

    public function id(): ?int { return $this->id; }
    public function withdrawalId(): int { return $this->withdrawalId; }
    public function attemptNo(): int { return $this->attemptNo; }
    public function status(): string { return $this->status; }
    public function broadcastFingerprint(): ?string { return $this->broadcastFingerprint; }
    public function txid(): ?string { return $this->txid; }
    public function broadcastDriver(): ?string { return $this->broadcastDriver; }
    public function rawTransactionHash(): ?string { return $this->rawTransactionHash; }
    public function rawTransaction(): ?string { return $this->rawTransaction; }
    public function requestPayload(): array { return $this->requestPayload; }
    public function responsePayload(): array { return $this->responsePayload; }
    public function errorMessage(): ?string { return $this->errorMessage; }
    public function broadcastedAt(): ?string { return $this->broadcastedAt; }
    public function confirmedAt(): ?string { return $this->confirmedAt; }
    public function failedAt(): ?string { return $this->failedAt; }

    public function storePreparedTransaction(
        string $fingerprint,
        string $rawTransactionHash,
        string $rawTransaction
    ): void {
        if ($this->status !== 'broadcasting') {
            throw new DomainException('Only broadcasting attempt can store prepared transaction.');
        }

        $this->broadcastFingerprint = $fingerprint;
        $this->rawTransactionHash = $rawTransactionHash;
        $this->rawTransaction = $rawTransaction;
        $this->assertInvariant();
    }

    public function markBroadcasted(string $txid, array $responsePayload = []): void
    {
        if ($this->status !== 'broadcasting') {
            throw new DomainException('Only broadcasting attempt can be marked broadcasted.');
        }

        $this->status = 'broadcasted';
        $this->txid = $txid;
        $this->responsePayload = $responsePayload;
        $this->broadcastedAt = now()->toDateTimeString();

        $this->recordDomainEvent(new WithdrawalAttemptBroadcasted(
            withdrawalId: $this->withdrawalId,
            attemptNo: $this->attemptNo,
            txid: $txid,
            broadcastDriver: $this->broadcastDriver,
        ));

        $this->assertInvariant();
    }

    public function markFailed(string $errorMessage): void
    {
        if ($this->status === 'confirmed') {
            throw new DomainException('Confirmed attempt cannot fail.');
        }

        $this->status = 'failed';
        $this->errorMessage = $errorMessage;
        $this->failedAt = now()->toDateTimeString();

        $this->recordDomainEvent(new WithdrawalAttemptFailed(
            withdrawalId: $this->withdrawalId,
            attemptNo: $this->attemptNo,
            reason: $errorMessage,
            txid: $this->txid,
        ));

        $this->assertInvariant();
    }

    public function markConfirmed(int $confirmations): void
    {
        if ($this->status !== 'broadcasted') {
            throw new DomainException('Only broadcasted attempt can be confirmed.');
        }

        $this->status = 'confirmed';
        $this->confirmedAt = now()->toDateTimeString();

        $this->recordDomainEvent(new WithdrawalAttemptConfirmed(
            withdrawalId: $this->withdrawalId,
            attemptNo: $this->attemptNo,
            txid: $this->txid ?? '',
            confirmations: $confirmations,
        ));

        $this->assertInvariant();
    }

    private function assertInvariant(): void
    {
        if ($this->withdrawalId <= 0) {
            throw new DomainException('withdrawalId must be positive.');
        }

        if ($this->attemptNo <= 0) {
            throw new DomainException('attemptNo must be positive.');
        }

        if ($this->status === '') {
            throw new DomainException('status cannot be empty.');
        }
    }
}
