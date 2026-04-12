<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

//use App\Domain\Shared\Outbox\OutboxMessage;
use App\Domain\Shared\Outbox\OutboxRepository;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentOutboxMessage;
use Illuminate\Support\Facades\DB;

final class EloquentOutboxRepository implements OutboxRepository
{
//    public function append(OutboxMessage $message): void
//    {
//        EloquentOutboxMessage::query()->create([
//            'idempotency_key' => $message->idempotencyKey,
//            'aggregate_type' => $message->aggregateType,
//            'aggregate_id' => (string) $message->aggregateId,
//            'event_type' => $message->eventType,
//            'payload' => $message->payload,
//            'status' => 'pending',
//            'attempts' => 0,
//            'available_at' => $message->availableAt,
//        ]);
//    }
    public function append(
        string $idempotencyKey,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void {
        EloquentOutboxMessage::query()->create([
            'idempotency_key' => $idempotencyKey,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => now(),
        ]);
    }

    public function fetchPending(int $batchSize = 100): array
    {
        return DB::transaction(function () use ($batchSize) {
            //return EloquentOutboxMessage::query()
            $messages =  EloquentOutboxMessage::query()
                ->where('status', 'pending')
                ->where(function ($q) {
                    $q->whereNull('available_at')->orWhere('available_at', '<=', now());
                }) // AND (`available_at` IS NULL OR `available_at` <= NOW())
                ->orderBy('id')
                ->limit($batchSize)
                ->lockForUpdate() // lock for update selected record
                ->get();
                //->all(); //all to array

            foreach ($messages as $message) {
                $message->status = 'processing';
                $message->locked_at = now();
                $message->attempts = $message->attempts + 1;
                $message->save();
            }
            return $messages;
        });
    }

    public function markDispatched(string $idempotencyKey): void
    {
        EloquentOutboxMessage::query()
            ->where('idempotency_key', $idempotencyKey)
            ->update([
                'status' => 'dispatched',
                'dispatched_at' => now(),
                'locked_at' => null,
                'last_error' => null,
            ]);
    }
    public function markRetryableFailure(string $idempotencyKey, string $error, int $delaySeconds): void
    {
        EloquentOutboxMessage::query()
            ->where('idempotency_key', $idempotencyKey)
            ->update([
                'status' => 'pending',
                'available_at' => now()->addSeconds($delaySeconds),
                'locked_at' => null,
                'last_error' => $error,
            ]);
    }

    public function markTerminalFailure(string $idempotencyKey, string $error): void
    {
        EloquentOutboxMessage::query()
            ->where('idempotency_key', $idempotencyKey)
            ->update([
                'status' => 'failed',
                'locked_at' => null,
                'last_error' => $error,
            ]);
    }

    public function reclaimStaleProcessing(int $staleMinutes = 15): int
    {
        return EloquentOutboxMessage::query()
            ->where('status', 'processing')
            ->where('locked_at', '<', now()->subMinutes($staleMinutes))
            ->update([
                'status' => 'pending',
                'available_at' => now(),
                'locked_at' => null,
                'last_error' => 'reclaimed stale processing row',
            ]);
    }
}
