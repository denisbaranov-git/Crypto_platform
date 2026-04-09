<?php

namespace App\Domain\Deposit\ValueObjects;

final readonly class ConfirmationRequirement
{
    private function __construct(
        public ConfirmationType $type,
        public int $requiredConfirmations,
    ) {}

    public static function blocks(int $confirmations): self
    {
        if ($confirmations < 1) {
            throw new \InvalidArgumentException('Blocks confirmations must be at least 1.');
        }

        return new self(ConfirmationType::Blocks, $confirmations);
    }

    public static function finality(): self
    {
        return new self(ConfirmationType::Finality, 0);
    }

    public function isBlocks(): bool
    {
        return $this->type === ConfirmationType::Blocks;
    }

    public function isFinality(): bool
    {
        return $this->type === ConfirmationType::Finality;
    }
}
