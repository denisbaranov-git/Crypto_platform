<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Shared\Outbox\OutboxMessage;
use App\Domain\Shared\Outbox\OutboxRepository;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentOutboxMessage;
use Illuminate\Support\Facades\DB;

final class EloquentOutboxRepository implements OutboxRepository
{
    public function append(OutboxMessage $message): void
    {
        EloquentOutboxMessage::query()->create([
            'idempotency_key' => $message->idempotencyKey,
            'aggregate_type' => $message->aggregateType,
            'aggregate_id' => (string) $message->aggregateId,
            'event_type' => $message->eventType,
            'payload' => $message->payload,
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => $message->availableAt,
        ]);
    }

    public function fetchPending(int $limit = 100): array
    {
        return DB::transaction(function () use ($limit) {
            return EloquentOutboxMessage::query()
                ->where('status', 'pending')
                ->where(function ($q) {
                    $q->whereNull('available_at')->orWhere('available_at', '<=', now());
                }) // AND (`available_at` IS NULL OR `available_at` <= NOW())
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate() // lock for update selected record
                ->get()
                ->all(); // to array
        });
    }

    public function markDispatched(string $idempotencyKey): void
    {
        EloquentOutboxMessage::query()
            ->where('idempotency_key', $idempotencyKey)
            ->update([
                'status' => 'dispatched',
                'dispatched_at' => now(),
                'last_error' => null,
            ]);
    }

    public function markFailed(string $idempotencyKey, string $error): void
    {
        EloquentOutboxMessage::query()
            ->where('idempotency_key', $idempotencyKey)
            ->update([
                'status' => 'failed',
                'last_error' => $error,
            ]);
    }
}
