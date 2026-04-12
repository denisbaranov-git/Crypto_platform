<?php

declare(strict_types=1);

namespace App\Application\Deposit\Commands;

final readonly class ReverseDepositCreditCommand
{
    public function __construct(
        public int $depositId,
        public array $metadata = [],
    ) {}
}
