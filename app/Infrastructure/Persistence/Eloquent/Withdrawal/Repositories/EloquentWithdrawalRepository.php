<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Withdrawal\Repositories;

use App\Domain\Withdrawal\Entities\Withdrawal;
use App\Domain\Withdrawal\Repositories\WithdrawalRepository;
use App\Infrastructure\Persistence\Eloquent\Mappers\WithdrawalMapper;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentWithdrawal;
use Illuminate\Support\Facades\DB;

final class EloquentWithdrawalRepository implements WithdrawalRepository
{
    public function __construct(
        private readonly WithdrawalMapper $mapper,
    ) {}

    public function save(Withdrawal $withdrawal): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal): Withdrawal {
            $model = $withdrawal->id()
                ? EloquentWithdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id()->value())
                : new EloquentWithdrawal();

            $this->mapper->toModel($model, $withdrawal);
            $model->save();

            return $this->mapper->toDomain($model->refresh());
        });
    }

    public function byId(int $id): ?Withdrawal
    {
        $model = EloquentWithdrawal::query()->find($id);

        return $model ? $this->mapper->toDomain($model) : null;
    }

    public function byIdempotencyKey(string $key): ?Withdrawal
    {
        $model = EloquentWithdrawal::query()
            ->where('idempotency_key', $key)
            ->first();

        return $model ? $this->mapper->toDomain($model) : null;
    }

    public function lockById(int $id): ?Withdrawal
    {
        $model = EloquentWithdrawal::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->first();

        return $model ? $this->mapper->toDomain($model) : null;
    }
    public function findOpenByNetwork(int $networkId, int $limit = 500): array
    {
        $rows = EloquentWithdrawal::query()
            ->where('network_id', $networkId)
            ->whereIn('status', ['broadcasted', 'settled'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return $rows->map(fn (EloquentWithdrawal $row) => $this->mapper->toDomain($row))->all();
    }
    public function findByNetworkAndBlockNumberBetween( int $networkId, int $rewindTo, int $oldLastProcessedBlock): array
    {
        $rows = EloquentWithdrawal::query()
            ->where('network_id', $networkId)
            ->whereNotNull('confirmed_block_number') // едесь не корректное название нужно -block_number //denis
            ->whereBetween('confirmed_block_number', [$rewindTo, $oldLastProcessedBlock])
            ->orderBy('confirmed_block_number')
            ->orderBy('id')
            ->get();

        return $rows->map(fn (EloquentWithdrawal $row) => $this->mapper->toDomain($row))->all();
    }

}
