<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\ValueObjects;

use DomainException;

final class WithdrawalStatus
{
    public const REQUESTED = 'requested';
    public const RESERVED = 'reserved';
    public const BROADCAST_PENDING = 'broadcast_pending';
    public const BROADCASTED = 'broadcasted';
    public const SETTLED = 'settled';
    public const CONFIRMED = 'confirmed';
    public const CANCELLED = 'cancelled';
    public const FAILED = 'failed';
    public const RELEASED = 'released';
    public const REORGED = 'reorged';
    public const REVERSED = 'reversed';
    public const MANUAL_REVIEW = 'manual_review';

    public function __construct(private readonly string $value)
    {
        if (! in_array($value, self::allowed(), true)) {
            throw new DomainException("Invalid withdrawal status [$value].");
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public static function allowed(): array
    {
        return [
            self::REQUESTED,
            self::RESERVED,
            self::BROADCAST_PENDING,
            self::BROADCASTED,
            self::SETTLED,
            self::CONFIRMED,
            self::CANCELLED,
            self::FAILED,
            self::RELEASED,
            self::REORGED,
            self::REVERSED,
            self::MANUAL_REVIEW,
        ];
    }
}
