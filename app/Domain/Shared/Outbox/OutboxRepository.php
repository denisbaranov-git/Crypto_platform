<?php

namespace App\Domain\Shared\Outbox;

interface OutboxRepository
{
    //public function append(OutboxMessage $message): void;
    public function append(
        string $idempotencyKey,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void;

    /**
     * @return object[] Eloquent rows or equivalent DTOs
     */
    public function fetchPending(int $limit = 100): array;
    public function markDispatched(string $idempotencyKey): void;
    public function markRetryableFailure(string $idempotencyKey, string $error, int $delaySeconds): void;
    public function markTerminalFailure(string $idempotencyKey, string $error): void;
    public function reclaimStaleProcessing(int $staleMinutes = 15): int;

}
