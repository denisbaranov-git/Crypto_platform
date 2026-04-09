<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Entities;

use App\Domain\Ledger\ValueObjects\Money;
use DomainException;

/**
 * Ledger Account aggregate.
 *
 * Это центральная сущность учёта.
 *
 * Почему aggregate:
 * - внутри неё живут инварианты по balance / reservedBalance;
 * - именно она защищает невозможные состояния;
 * - именно она должна управлять локальной целостностью денег.
 *
 * Важно:
 * - balance = фактический баланс;
 * - reservedBalance = заблокированные средства;
 * - available = balance - reservedBalance;
 *
 * Для crypto здесь используется currencyNetworkId, а не currencyId,
 * потому что USDT ERC20 и USDT TRC20 - это разные operational assets.
 */
final class Account
{
    public function __construct(
        private ?int $id,
        private string $ownerType,
        private int $ownerId,
        private int $currencyNetworkId,
        private Money $balance,
        private Money $reservedBalance,
        private string $status = 'active',
        private int $version = 0,
        private array $metadata = [],
    ) {
        $this->assertInvariant();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function ownerType(): string
    {
        return $this->ownerType;
    }

    public function ownerId(): int
    {
        return $this->ownerId;
    }

    public function currencyNetworkId(): int
    {
        return $this->currencyNetworkId;
    }

    public function balance(): Money
    {
        return $this->balance;
    }

    public function reservedBalance(): Money
    {
        return $this->reservedBalance;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * Быстрый доступный баланс.
     * Важно: это не отдельное хранилище, а derived value.
     */
    public function availableBalance(): Money
    {
        return new Money(
            bcsub($this->balance->amount, $this->reservedBalance->amount, 18)
        );
    }

    /**
     * Начисление.
     * Используется для:
     * - deposit credit
     * - fiat credit
     * - transfer incoming
     * - fee reversal / compensation
     */
    public function credit(Money $amount): void
    {
        $this->assertActive();
        $this->assertPositive($amount);

        $this->balance = $this->balance->add($amount);
        $this->version++;

        $this->assertInvariant();
    }

    /**
     * Списание.
     * Используется для:
     * - fee
     * - transfer outgoing
     * - manual adjustment out
     *
     * Списание идёт только из available balance.
     */
    public function debit(Money $amount): void
    {
        $this->assertActive();
        $this->assertPositive($amount);

        if ($this->availableBalance()->greaterThanOrEqual($amount) === false) {
            throw new DomainException('Insufficient available balance.');
        }

        $this->balance = $this->balance->sub($amount);
        $this->version++;

        $this->assertInvariant();
    }

    /**
     * Резервирование средств.
     * Используется для:
     * - withdrawal request
     * - collateral lock
     * - AML freeze / hold flows
     */
    public function reserve(Money $amount): void
    {
        $this->assertActive();
        $this->assertPositive($amount);

        if ($this->availableBalance()->greaterThanOrEqual($amount) === false) {
            throw new DomainException('Insufficient available balance to reserve.');
        }

        $this->reservedBalance = $this->reservedBalance->add($amount);
        $this->version++;

        $this->assertInvariant();
    }

    /**
     * Освобождение резерва.
     * Используется когда:
     * - withdrawal отменён;
     * - hold истёк;
     * - manual release.
     */
    public function releaseReservation(Money $amount): void
    {
        $this->assertActive();
        $this->assertPositive($amount);

        if ($this->reservedBalance->greaterThanOrEqual($amount) === false) {
            throw new DomainException('Cannot release more than reserved.');
        }

        $this->reservedBalance = $this->reservedBalance->sub($amount);
        $this->version++;

        $this->assertInvariant();
    }

    /**
     * Потребление резерва.
     * Сценарий:
     * - деньги уже были зарезервированы;
     * - операция выводится в сеть / окончательно списывается;
     * - reserved уменьшается;
     * - balance тоже уменьшается.
     *
     * Это не то же самое, что debit().
     */
    public function consumeReservation(Money $amount): void
    {
        $this->assertActive();
        $this->assertPositive($amount);

        if ($this->reservedBalance->greaterThanOrEqual($amount) === false) {
            throw new DomainException('Cannot consume more than reserved.');
        }

        if ($this->balance->greaterThanOrEqual($amount) === false) {
            throw new DomainException('Cannot consume more than balance.');
        }

        $this->reservedBalance = $this->reservedBalance->sub($amount);
        $this->balance = $this->balance->sub($amount);
        $this->version++;

        $this->assertInvariant();
    }

    public function lock(): void
    {
        $this->status = 'locked';
        $this->version++;
        $this->assertInvariant();
    }

    public function unlock(): void
    {
        $this->status = 'active';
        $this->version++;
        $this->assertInvariant();
    }

    public function close(): void
    {
        $this->status = 'closed';
        $this->version++;
        $this->assertInvariant();
    }

    public function withId(int $id): self
    {
        $clone = clone $this;
        $clone->id = $id;

        return $clone;
    }

    private function assertActive(): void
    {
        if ($this->status !== 'active') {
            throw new DomainException('Account is not active.');
        }
    }

    private function assertPositive(Money $amount): void
    {
        if ($amount->isZero()) {
            throw new DomainException('Amount must be greater than zero.');
        }
    }

    private function assertInvariant(): void
    {
        if (bccomp($this->balance->amount, '0', 18) < 0) {
            throw new DomainException('Balance cannot be negative.');
        }

        if (bccomp($this->reservedBalance->amount, '0', 18) < 0) {
            throw new DomainException('Reserved balance cannot be negative.');
        }

        if (bccomp($this->reservedBalance->amount, $this->balance->amount, 18) > 0) {
            throw new DomainException('Reserved balance cannot exceed balance.');
        }
    }
}
