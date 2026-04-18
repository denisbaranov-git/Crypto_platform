<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Ledger\Repositories;

use App\Domain\Ledger\Entities\LedgerHold;
use App\Domain\Ledger\Repositories\LedgerHoldRepository;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentLedgerHold;
use Illuminate\Support\Facades\DB;

final class EloquentLedgerHoldRepository implements LedgerHoldRepository
{
    public function findById(int $id): ?LedgerHold
    {
        $model = EloquentLedgerHold::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByIdForUpdate(int $id): ?LedgerHold
    {
        $model = EloquentLedgerHold::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->first();

        return $model ? $this->toDomain($model) : null;
    }
    public function findByLedgerOperationId(string $ledgerOperationId): ?LedgerHold
    {
        $model = EloquentLedgerHold::query()
            ->where('ledger_operation_id', $ledgerOperationId)
            ->first();

        if (! $model) {
            return null;
        }

        return new LedgerHold(
            id: (int) $model->id,
            ledgerOperationId: (string) $model->ledger_operation_id,
            accountId: (int) $model->account_id,
            currencyNetworkId: (int) $model->currency_network_id,
            amount: (string) $model->amount,
            status: (string) $model->status,
            reason: $model->reason,
            expiresAt: $model->expires_at?->toDateTimeString(),
            releasedAt: $model->released_at?->toDateTimeString(),
            consumedAt: $model->consumed_at?->toDateTimeString(),
            metadata: $model->metadata ?? [],
        );
    }
//    public function findActiveByLedgerOperationId(string $ledgerOperationId): ?LedgerHold
//    {
//        $model = EloquentLedgerHold::query()
//            ->where('ledger_operation_id', $ledgerOperationId)
//            ->where('status', 'active')
//            ->first();
//
//        return $model ? $this->toDomain($model) : null;
//    }

    public function save(LedgerHold $hold): LedgerHold
    {
        $model = $hold->id()
            ? EloquentLedgerHold::query()->whereKey($hold->id())->first()
            : new EloquentLedgerHold();

        if ($model === null) {
            $model = new EloquentLedgerHold();
        }

        $model->ledger_operation_id = $hold->ledgerOperationId();
        $model->account_id = $hold->accountId();
        $model->currency_network_id = $hold->currencyNetworkId();
        $model->amount = $hold->amount();
        $model->status = $hold->status();
        $model->reason = $hold->reason();
        $model->expires_at = $hold->expiresAt();
        $model->metadata = $hold->metadata();

        $model->save();

        return $this->toDomain($model);
    }

    private function toDomain(EloquentLedgerHold $model): LedgerHold
    {
        return new LedgerHold(
            id: $model->id,
            ledgerOperationId: $model->ledger_operation_id,
            accountId: (int) $model->account_id,
            currencyNetworkId: (int) $model->currency_network_id,
            amount: (string) $model->amount,
            status: $model->status,
            reason: $model->reason,
            expiresAt: $model->expires_at?->toDateTimeString(),
            releasedAt: $model->released_at?->toDateTimeString(),
            consumedAt: $model->consumed_at?->toDateTimeString(),
            metadata: $model->metadata ?? [],
        );
    }
}
