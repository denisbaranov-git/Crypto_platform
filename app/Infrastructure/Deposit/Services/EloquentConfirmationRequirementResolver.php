<?php

namespace App\Infrastructure\Deposit\Services;

use App\Domain\Deposit\Services\ConfirmationRequirementResolver;
use App\Domain\Deposit\ValueObjects\ConfirmationRequirement;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentConfirmationRule;
use Illuminate\Support\Facades\DB;

final class EloquentConfirmationRequirementResolver implements ConfirmationRequirementResolver
{
    public function resolve(int $currencyNetworkId, string $amount): ConfirmationRequirement
    {
        $rules = EloquentConfirmationRule::query()
            ->where('currency_network_id', $currencyNetworkId)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderByDesc('amount_threshold')
            ->get();

        foreach ($rules as $rule) {
            if (! $this->matchesAmount($amount, $rule->amount_threshold)) {
                continue;
            }

            if ($rule->confirmation_type === 'finality') {
                return ConfirmationRequirement::finality();
            }

            return ConfirmationRequirement::blocks((int) $rule->confirmations_required);
        }

        $fallback = DB::table('currency_networks')
            ->where('id', $currencyNetworkId)
            ->value('min_confirmations');

        return ConfirmationRequirement::blocks((int) ($fallback ?: config('blockchain.confirmations.default_blocks', 12)));
    }

    private function matchesAmount(string $amount, mixed $threshold): bool
    {
        if ($threshold === null) {
            return true;
        }

        if (function_exists('bccomp')) {
            return bccomp($amount, (string) $threshold, 18) >= 0;
        }

        throw new \RuntimeException('BCMath extension is required for decimal comparison.');
    }
}
