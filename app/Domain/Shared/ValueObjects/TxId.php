<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use DomainException;

final class TxId
{
    public function __construct(private string $value)
    {
        //$value = trim($value);
        $value = strtolower(trim($value));

        if ($value === '') {
            throw new DomainException('TxId cannot be empty.');
        }

        if (mb_strlen($value) > 255) {
            throw new DomainException('TxId is too long.');
        }

        $this->value = $value;
    }
    public static function fromString(string $value): self
    {
        return new self($value);
    }
    public function value(): string
    {
        return $this->value;
    }
}
