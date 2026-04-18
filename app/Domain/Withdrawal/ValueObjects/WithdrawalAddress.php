<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\ValueObjects;

use DomainException;

final class WithdrawalAddress
{
    public function __construct(private string $value)
    {
        $value = trim($value);

        if ($value === '') {
            throw new DomainException('Withdrawal address cannot be empty.');
        }

        if (mb_strlen($value) > 255) {
            throw new DomainException('Withdrawal address is too long.');
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }
}
