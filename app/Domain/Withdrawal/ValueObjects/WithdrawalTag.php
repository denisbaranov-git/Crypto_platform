<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\ValueObjects;

use DomainException;

final class WithdrawalTag
{
    public function __construct(private ?string $value)
    {
        if ($value === null) {
            return;
        }

        $value = trim($value);

        if ($value === '') {
            throw new DomainException('Withdrawal tag cannot be empty string.');
        }

        if (mb_strlen($value) > 255) {
            throw new DomainException('Withdrawal tag is too long.');
        }

        $this->value = $value;
    }

    public function value(): ?string
    {
        return $this->value;
    }
}
