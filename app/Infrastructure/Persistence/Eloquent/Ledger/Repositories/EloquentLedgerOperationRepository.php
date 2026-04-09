<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Ledger\Repositories;

use App\Domain\Ledger\Entities\LedgerOperation;
use App\Domain\Ledger\Repositories\LedgerOperationRepository;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentLedgerOperation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EloquentLedgerOperationRepository implements LedgerOperationRepository
{
    public function findByIdempotencyKey(string $idempotencyKey): ?LedgerOperation
    {
        $model = EloquentLedgerOperation::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($model === null) {
            return null;
        }

        return new LedgerOperation(
            id: $model->id,
            idempotencyKey: $model->idempotency_key,
            type: $model->type,
            status: $model->status,
            referenceType: $model->reference_type,
            referenceId: $model->reference_id,
            description: $model->description,
            metadata: $model->metadata ?? [],
            postedAt: $model->posted_at?->toDateTimeString(),
            failedAt: $model->failed_at?->toDateTimeString(),
        );
    }

    public function save(LedgerOperation $operation): LedgerOperation
    {
        return DB::transaction(function () use ($operation) {
            $model = EloquentLedgerOperation::query()
                ->where('id', $operation->id())
                ->first();

            if ($model === null) {
                $model = new EloquentLedgerOperation();
                $model->id = $operation->id();
            }

            $model->idempotency_key = $operation->idempotencyKey();
            $model->type = $operation->type();
            $model->status = $operation->status();
            $model->reference_type = $operation->referenceType();
            $model->reference_id = $operation->referenceId();
            $model->description = $operation->description();
            $model->metadata = $operation->metadata();

            $model->posted_at = $operation->postedAt()
                ? Carbon::parse($operation->postedAt())
                : null;

            $model->failed_at = $operation->failedAt()
                ? Carbon::parse($operation->failedAt())
                : null;

            $model->save();

            return $operation;
        });
    }
}
