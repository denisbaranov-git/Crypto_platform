<?php

namespace App\Domain\Shared\Outbox;

interface OutboxRepository
{
    public function append(OutboxMessage $message): void;

    /**
     * @return object[] Eloquent rows or equivalent DTOs
     */
    public function fetchPending(int $limit = 100): array;

    public function markDispatched(string $idempotencyKey): void;

    public function markFailed(string $idempotencyKey, string $error): void;
}
