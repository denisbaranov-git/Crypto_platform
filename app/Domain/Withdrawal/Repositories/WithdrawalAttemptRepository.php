<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Repositories;

use App\Domain\Withdrawal\Entities\WithdrawalAttempt;

interface WithdrawalAttemptRepository
{
    public function save(WithdrawalAttempt $attempt): WithdrawalAttempt;

    public function nextAttemptNo(int $withdrawalId): int;

    public function latestForWithdrawal(int $withdrawalId): ?WithdrawalAttempt;
}
