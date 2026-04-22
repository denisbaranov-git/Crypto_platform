<?php

declare(strict_types=1);

namespace App\Infrastructure\Withdrawal\Services;

use App\Domain\Shared\ValueObjects\Amount;
use App\Domain\Withdrawal\Services\WithdrawalEligibilityPolicy;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentCurrencyNetwork;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentUser;
use DomainException;

/**
 * WithdrawalEligibilityPolicy проверяет user/network/pair/limits
*/
final class EloquentWithdrawalEligibilityPolicy implements WithdrawalEligibilityPolicy
{
    public function assertCanWithdraw(
        int $userId,
        int $networkId,
        int $currencyNetworkId,
        Amount $amount,
        array $context = []
    ): void {
        $user = EloquentUser::query()->find($userId);

        if (! $user || $user->status !== 'active') {
            throw new DomainException('User is not active.');
        }

        $pair = EloquentCurrencyNetwork::query()
            ->whereKey($currencyNetworkId)
            ->where('network_id', $networkId)
            ->first();

        if (! $pair || ! $pair->is_active || ! $pair->is_withdrawal_enabled) {
            throw new DomainException('Withdrawal is not enabled for this currency-network pair.');
        }

        if (bccomp($amount->value(), (string) $pair->min_withdrawal_amount, 18) < 0) {
            throw new DomainException('Amount is below minimum withdrawal amount.');
        }

        if ($pair->max_withdrawal_amount !== null && bccomp($amount->value(), (string) $pair->max_withdrawal_amount, 18) > 0) {
            throw new DomainException('Amount is above maximum withdrawal amount.');
        }
    }
}
