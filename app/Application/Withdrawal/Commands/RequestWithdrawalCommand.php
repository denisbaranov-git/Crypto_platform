<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Commands;

final readonly class RequestWithdrawalCommand
{
    public function __construct(
        public int $userId,
        public int $networkId,
        public int $currencyNetworkId,
        public string $destinationAddress,
        public ?string $destinationTag,
        public string $amount,
        public string $idempotencyKey,
        public array $metadata = [],
    ) {}
}
