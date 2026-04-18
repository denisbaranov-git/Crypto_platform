<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Withdrawal\Repositories;

use App\Domain\Withdrawal\Entities\WithdrawalAttempt;
use App\Domain\Withdrawal\Repositories\WithdrawalAttemptRepository;
use App\Infrastructure\Persistence\Eloquent\Mappers\WithdrawalAttemptMapper;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentWithdrawalAttempt;
use Illuminate\Support\Facades\DB;

final class EloquentWithdrawalAttemptRepository implements WithdrawalAttemptRepository
{
    public function __construct(
        private readonly WithdrawalAttemptMapper $mapper,
    ) {}

    public function save(WithdrawalAttempt $attempt): WithdrawalAttempt
    {
        return DB::transaction(function () use ($attempt): WithdrawalAttempt {
            $model = EloquentWithdrawalAttempt::query()
                ->where('withdrawal_id', $attempt->withdrawalId())
                ->where('attempt_no', $attempt->attemptNo())
                ->first() ?? new EloquentWithdrawalAttempt();

            $this->mapper->fillModel($model, $attempt);
            $model->save();

            return $this->mapper->toDomain($model->refresh());
        });
    }

    public function nextAttemptNo(int $withdrawalId): int
    {
        return ((int) EloquentWithdrawalAttempt::query()
                ->where('withdrawal_id', $withdrawalId)
                ->max('attempt_no')) + 1;
    }

    public function latestForWithdrawal(int $withdrawalId): ?WithdrawalAttempt
    {
        $model = EloquentWithdrawalAttempt::query()
            ->where('withdrawal_id', $withdrawalId)
            ->orderByDesc('attempt_no')
            ->first();

        return $model ? $this->mapper->toDomain($model) : null;
    }
}
