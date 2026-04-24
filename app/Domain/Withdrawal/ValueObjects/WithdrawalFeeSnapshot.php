<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\ValueObjects;

use DomainException;

final readonly class WithdrawalFeeSnapshot
{
    public function __construct(
        private string $feeRuleId,
        private string $feeType,
        private string $fee,
        private ?string $minAmount,
        private ?string $maxAmount,
        private int $priority,
        private array $metadata = [],
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

    public static function fromArray(array $data): self
    {
        return new self(
            feeRuleId: (string) ($data['fee_rule_id'] ?? $data['feeRuleId'] ?? ''),
            feeType: (string) ($data['fee_type'] ?? $data['feeType'] ?? ''),
            fee: (string) ($data['fee'] ?? '0'),
            minAmount: isset($data['min_amount']) ? (string) $data['min_amount'] : (isset($data['minAmount']) ? (string) $data['minAmount'] : null),
            maxAmount: isset($data['max_amount']) ? (string) $data['max_amount'] : (isset($data['maxAmount']) ? (string) $data['maxAmount'] : null),
            priority: (int) ($data['priority'] ?? 0),
            metadata: (array) ($data['metadata'] ?? [])
        );
    }

    public function feeRuleId(): string { return $this->feeRuleId; }
    public function feeType(): string { return $this->feeType; }
    public function fee(): string { return $this->fee; }
    public function minAmount(): ?string { return $this->minAmount; }
    public function maxAmount(): ?string { return $this->maxAmount; }
    public function priority(): int { return $this->priority; }
    public function metadata(): array { return $this->metadata; }

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
