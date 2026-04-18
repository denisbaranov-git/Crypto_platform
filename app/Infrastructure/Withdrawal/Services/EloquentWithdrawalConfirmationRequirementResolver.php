<?php

declare(strict_types=1);

namespace App\Infrastructure\Withdrawal\Services;

use App\Domain\Withdrawal\Services\WithdrawalConfirmationRequirementResolver;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentConfirmationRule;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentCurrencyNetwork;
use DomainException;

final class EloquentWithdrawalConfirmationRequirementResolver implements WithdrawalConfirmationRequirementResolver
{
    public function resolve(int $currencyNetworkId, string $amount): int
    {
        $pair = EloquentCurrencyNetwork::query()->find($currencyNetworkId);

        if (! $pair) {
            throw new DomainException('Currency-network pair not found.');
        }

        if ($pair->use_finality && (
                $pair->finality_threshold === null ||
                bccomp($amount, (string) $pair->finality_threshold, 18) >= 0
            )) {
            return (int) ($pair->finalization_blocks ?? $pair->min_confirmations ?? 12);
        }

        $rule = EloquentConfirmationRule::query()
            ->where('currency_network_id', $currencyNetworkId)
            ->where('is_active', true)
            ->where(function ($q) use ($amount): void {
                $q->whereNull('amount_threshold')
                    ->orWhere('amount_threshold', '<=', $amount);
            })
            ->orderByDesc('priority')
            ->orderByDesc('amount_threshold')
            ->first();

        if ($rule) {
            return (int) $rule->confirmations_required;
        }

        return (int) ($pair->min_confirmations ?? 12);
    }
}
