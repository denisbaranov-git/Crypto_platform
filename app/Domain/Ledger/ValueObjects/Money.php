<?php

declare(strict_types=1);

namespace App\Domain\Ledger\ValueObjects;

use InvalidArgumentException;

/**
 * Money value object.
 *
 * Зачем нужен:
 * - убирает float-ошибки;
 * - централизует операции с decimal-значениями;
 * - гарантирует, что сумма не станет "грязной" строкой по всему коду.
 *
 * Важно:
 * - мы используем string + bc*;
 * - precision фиксируем на 18 знаков после запятой,
 *   потому что для crypto этого обычно достаточно.
 */
final readonly class Money
{
    public function __construct(
        public string $amount
    ) {
        $this->assertValid($amount);
    }

    public static function zero(): self
    {
        return new self('0');
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', 18) === 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->amount, '0', 18) > 0;
    }

    public function add(self $other): self
    {
        return new self(bcadd($this->amount, $other->amount, 18));
    }

    public function sub(self $other): self
    {
        if (bccomp($this->amount, $other->amount, 18) < 0) {
            throw new InvalidArgumentException('Cannot subtract larger amount from smaller amount.');
        }

        return new self(bcsub($this->amount, $other->amount, 18));
    }

    public function greaterThan(self $other): bool
    {
        return bccomp($this->amount, $other->amount, 18) > 0;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return bccomp($this->amount, $other->amount, 18) >= 0;
    }

    public function equals(self $other): bool
    {
        return bccomp($this->amount, $other->amount, 18) === 0;
    }

    private function assertValid(string $amount): void
    {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Money amount must be numeric.');
        }

        // Никаких отрицательных сумм внутри Money.
        // Направление движения денег мы моделируем через direction / action,
        // а не через отрицательное число.
        if (bccomp($amount, '0', 18) < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative.');
        }
    }
}
