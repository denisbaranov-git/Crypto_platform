<?php

declare(strict_types=1);

namespace App\Application\Withdrawal\Commands;

final readonly class UpdateWithdrawalConfirmationsCommand
{
    public function __construct(
        public int $withdrawalId,
        public int $networkId,
        public int $currencyNetworkId,
        public string $txid,
        public int $confirmations,
        public ?string $blockHash = null,
        public ?int $blockNumber = null,
        public bool $finalized = false,
        public array $metadata = [],
    ) {}
}
