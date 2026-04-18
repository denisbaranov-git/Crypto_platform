<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\ValueObjects;

use DomainException;

final class WithdrawalFeeSnapshot
{
    public function __construct(
        private readonly string $feeRuleId,
        private readonly string $feeType,
        private readonly string $fee,
        private readonly ?string $minAmount = null,
        private readonly ?string $maxAmount = null,
        private readonly ?int $priority = null,
        private readonly array $metadata = [],
    ) {
        if ($feeRuleId === '') {
            throw new DomainException('feeRuleId cannot be empty.');
        }

        if (! in_array($feeType, ['fixed', 'percent'], true)) {
            throw new DomainException('Invalid feeType.');
        }

        if (! preg_match('/^\d+(\.\d{1,18})?$/', $fee) || bccomp($fee, '0', 18) < 0) {
            throw new DomainException('Invalid fee value.');
        }
    }

    public function toArray(): array
    {
        return [
            'fee_rule_id' => $this->feeRuleId,
            'fee_type' => $this->feeType,
            'fee' => $this->fee,
            'min_amount' => $this->minAmount,
            'max_amount' => $this->maxAmount,
            'priority' => $this->priority,
            'metadata' => $this->metadata,
        ];
    }
}
