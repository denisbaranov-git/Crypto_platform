<?php

declare(strict_types=1);

namespace App\Infrastructure\Withdrawal\Services;

use App\Domain\Withdrawal\Services\WithdrawalFeeCalculator;
use App\Domain\Shared\ValueObjects\Amount;
use App\Domain\Withdrawal\ValueObjects\WithdrawalFeeSnapshot;
use App\Infrastructure\Persistence\Eloquent\Models\EloquentFeeRule;
use DomainException;

/**
 * WithdrawalFeeCalculator выбирает fee_rule
 */
final class EloquentWithdrawalFeeCalculator implements WithdrawalFeeCalculator
{
    public function quote(int $currencyNetworkId, Amount $amount, array $context = []): WithdrawalFeeSnapshot
    {
        $rule = EloquentFeeRule::query()
            ->where('currency_network_id', $currencyNetworkId)
            ->where(function ($q) use ($amount): void {
                $q->whereNull('min_amount')->orWhere('min_amount', '<=', $amount->value());
            })
            ->where(function ($q) use ($amount): void {
                $q->whereNull('max_amount')->orWhere('max_amount', '>=', $amount->value());
            })
            ->orderByDesc('priority')
            ->orderBy('id')
            ->first();

        if (! $rule) {
            throw new DomainException('No fee rule found for withdrawal.');
        }

        return new WithdrawalFeeSnapshot(
            feeRuleId: (string) $rule->id,
            feeType: (string) $rule->fee_type,
            fee: (string) $rule->fee,
            minAmount: $rule->min_amount,
            maxAmount: $rule->max_amount,
            priority: (int) $rule->priority,
            metadata: $context
        );
    }

    public function calculateFeeAmount(Amount $amount, WithdrawalFeeSnapshot $snapshot): string
    {
        $data = $snapshot->toArray();

        return match ($data['fee_type']) {
            'fixed' => (string) $data['fee'],
            'percent' => bcdiv(bcmul($amount->value(), (string) $data['fee'], 18), '100', 18),
            default => throw new DomainException('Unsupported fee type.'),
        };
    }

    public function calculateTotalDebitAmount(Amount $amount, string $feeAmount): string
    {
        return bcadd($amount->value(), $feeAmount, 18);
    }
}
