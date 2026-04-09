<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Deposit\Entities\Deposit;
use App\Domain\Deposit\Repositories\DepositRepository;
use App\Domain\Deposit\ValueObjects\DepositId;
use App\Domain\Deposit\ValueObjects\ExternalKey;
use App\Infrastructure\Persistence\Eloquent\Mappers\DepositMapper;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentDeposit;

final class EloquentDepositRepository implements DepositRepository
{
    public function __construct(
        private readonly DepositMapper $mapper
    ) {}

    public function save(Deposit $deposit): Deposit
    {
        $model = null;

        if ($deposit->id() !== null) {
            $model = EloquentDeposit::query()->find($deposit->id()->value());
        }

        if (! $model) {
            $model = EloquentDeposit::query()->firstOrNew([
                'network_id' => $deposit->networkId(),
                'external_key' => $deposit->externalKey()->value(),
            ]);
        }

        $model = $this->mapper->toModel($deposit, $model);
        $model->save();

        return $this->mapper->toEntity($model->refresh());
    }

    public function findById(DepositId $id): ?Deposit
    {
        $model = EloquentDeposit::query()->find($id->value());

        return $model ? $this->mapper->toEntity($model) : null;
    }

    public function findByExternalKey(int $networkId, ExternalKey $externalKey): ?Deposit
    {
        $model = EloquentDeposit::query()
            ->where('network_id', $networkId)
            ->where('external_key', $externalKey->value())
            ->first();

        return $model ? $this->mapper->toEntity($model) : null;
    }

    public function existsByExternalKey(int $networkId, ExternalKey $externalKey): bool
    {
        return EloquentDeposit::query()
            ->where('network_id', $networkId)
            ->where('external_key', $externalKey->value())
            ->exists();
    }

    public function findOpenByNetwork(int $networkId, int $limit = 500): array
    {
        $rows = EloquentDeposit::query()
            ->where('network_id', $networkId)
            ->whereIn('status', ['detected', 'pending', 'confirmed'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return $rows->map(fn (EloquentDeposit $row) => $this->mapper->toEntity($row))->all();
    }

    public function findByNetworkAndBlockNumberGreaterThan(int $networkId, int $blockNumber): array
    {
        $rows = EloquentDeposit::query()
            ->where('network_id', $networkId)
            ->whereNotNull('block_number')
            ->where('block_number', '>', $blockNumber)
            ->orderBy('block_number')
            ->get();

        return $rows->map(fn (EloquentDeposit $row) => $this->mapper->toEntity($row))->all();
    }
}
